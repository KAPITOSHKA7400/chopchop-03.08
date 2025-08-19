package main

import (
    "database/sql"
    "log"
    "os"
    "strings"
    "time"

    tgbotapi "github.com/go-telegram-bot-api/telegram-bot-api"
    _ "github.com/go-sql-driver/mysql"
)

func main() {
    // Московское время
    loc, err := time.LoadLocation("Europe/Moscow")
    if err != nil {
        log.Fatal("Не удалось загрузить зону Europe/Moscow:", err)
    }
    time.Local = loc

    // DSN из окружения
    dsn := os.Getenv("DB_DSN")
    if dsn == "" {
        log.Fatal("DB_DSN не задан")
    }

    active := make(map[string]struct{})
    checkAndLaunch := func() {
        db, err := sql.Open("mysql", dsn)
        if err != nil {
            log.Println("Ошибка подключения к базе:", err)
            return
        }
        defer db.Close()

        rows, err := db.Query("SELECT bot_token FROM bots")
        if err != nil {
            log.Println("Ошибка выборки токенов:", err)
            return
        }
        defer rows.Close()

        for rows.Next() {
            var rawToken string
            if err := rows.Scan(&rawToken); err != nil {
                log.Println("Ошибка чтения токена:", err)
                continue
            }
            token := strings.TrimSpace(rawToken)
            if _, ok := active[token]; !ok {
                go runBot(token, dsn)
                active[token] = struct{}{}
                log.Println("Запущен бот для токена:", token)
            }
        }
    }

    // первый запуск
    checkAndLaunch()
    ticker := time.NewTicker(30 * time.Second)
    defer ticker.Stop()
    for range ticker.C {
        checkAndLaunch()
    }
}

func runBot(botToken, dsn string) {
    db, err := sql.Open("mysql", dsn)
    if err != nil {
        log.Println("Ошибка подключения к базе в runBot:", err)
        return
    }
    defer db.Close()

    if _, err := db.Exec("SET time_zone = '+03:00'"); err != nil {
        log.Println("Не удалось выставить time_zone:", err)
    }

    // Получим bot_id
    var botID int64
    if err := db.QueryRow("SELECT id FROM bots WHERE bot_token = ?", botToken).Scan(&botID); err != nil {
        log.Println("Не удалось получить bot_id:", err)
        return
    }

    // Инициализируем Telegram API
    bot, err := tgbotapi.NewBotAPI(botToken)
    if err != nil {
        log.Println("Неверный токен:", err)
        return
    }
    log.Printf("Бот @%s запущен\n", bot.Self.UserName)

    u := tgbotapi.NewUpdate(0)
    u.Timeout = 60
    updates, err := bot.GetUpdatesChan(u)
    if err != nil {
        log.Println("Не удалось получить канал обновлений:", err)
        return
    }

    for update := range updates {
        if update.Message == nil {
            continue
        }

        // 1) Команды
        if update.Message.IsCommand() {
            cmd := update.Message.Command()
            userID := int64(update.Message.From.ID)
            chatID := update.Message.Chat.ID
            log.Printf("Получена команда: /%s от %d\n", cmd, userID)

            if cmd == "start" {
                // Сохраняем факт нажатия /start
                if _, err := db.Exec(
                    `INSERT INTO chat_message
                       (bot_token, chat_id, telegram_user_id, username, text, created_at)
                     VALUES(?, ?, ?, ?, ?, NOW())`,
                    botToken,
                    chatID,
                    userID,
                    update.Message.From.UserName,
                    "/start",
                ); err != nil {
                    log.Println("Ошибка вставки /start:", err)
                }

                // Отправляем и сохраняем авто-ответ
                sendStartTemplate(bot, db, botID, chatID, userID)
            }
            continue
        }

        // 2) Обычные сообщения от пользователя
        user := update.Message.From
        chatID := update.Message.Chat.ID
        text := update.Message.Text

        // Upsert пользователя
        avatarURL := fetchAvatarURL(bot, user.ID)
        saveChatUserToDB(db, botID, user, avatarURL)

        // Сохраняем текст в chat_message
        if _, err := db.Exec(
            `INSERT INTO chat_message
               (bot_token, chat_id, telegram_user_id, username, text, created_at)
             VALUES(?, ?, ?, ?, ?, NOW())`,
            botToken,
            chatID,
            user.ID,
            user.UserName,
            text,
        ); err != nil {
            log.Println("Ошибка вставки сообщения:", err)
        } else {
            log.Printf("chat_message saved: chat=%d user=%d text=%q\n", chatID, user.ID, text)
        }
    }
}

// sendStartTemplate шлёт вложения + текст и сохраняет авто-ответ с флагом is_auto
func sendStartTemplate(
    bot *tgbotapi.BotAPI,
    db *sql.DB,
    botID, chatID, userID int64,
) {
    log.Printf("sendStartTemplate: ищем шаблон для bot_id=%d\n", botID)

    // 1) Берём шаблон
    var tplID int64
    var bodyText string
    err := db.QueryRow(
        "SELECT id, body FROM bot_msg_templates WHERE bot_id = ? AND type = 'start' AND is_active = 1 LIMIT 1",
        botID,
    ).Scan(&tplID, &bodyText)
    if err != nil {
        log.Println("sendStartTemplate: SELECT error:", err)
        return
    }

    // 2) Отправляем файлы
    rows, err := db.Query(
        "SELECT file_path, file_mime FROM bot_msg_files WHERE template_id = ?",
        tplID,
    )
    if err == nil {
        defer rows.Close()
        for rows.Next() {
            var path, mime string
            if err := rows.Scan(&path, &mime); err != nil {
                log.Println("sendStartTemplate: scan file error:", err)
                continue
            }
            fullPath := "storage/" + path
            var cfg tgbotapi.Chattable
            switch {
            case strings.HasPrefix(mime, "image/"):
                cfg = tgbotapi.NewPhotoUpload(chatID, fullPath)
            case strings.HasPrefix(mime, "video/"):
                cfg = tgbotapi.NewVideoUpload(chatID, fullPath)
            case strings.HasPrefix(mime, "audio/"):
                cfg = tgbotapi.NewAudioUpload(chatID, fullPath)
            default:
                cfg = tgbotapi.NewDocumentUpload(chatID, fullPath)
            }
            if _, err := bot.Send(cfg); err != nil {
                log.Println("sendStartTemplate: send file error:", err)
            }
        }
    }

    // 3) Отправляем текст
    msg := tgbotapi.NewMessage(chatID, bodyText)
    msg.ParseMode = "HTML"
    resp, err := bot.Send(msg)
    if err != nil {
        log.Println("sendStartTemplate: send text error:", err)
    } else {
        log.Printf("sendStartTemplate: sent text message_id=%d\n", resp.MessageID)
    }

    // 4) Сохраняем авто-ответ в chat_message с флагом is_auto = 1
    if _, err := db.Exec(
        `INSERT INTO chat_message
           (bot_token, chat_id, telegram_user_id, username, text, is_auto, created_at)
         VALUES(?, ?, ?, ?, ?, ?, NOW())`,
        bot.Token,           // бот-токен
        chatID,              // чат
        userID,              // приклеим к тому же пользователю
        "Авто-сообщение",    // подпись
        bodyText,            // текст
        true,                // is_auto = 1
    ); err != nil {
        log.Println("Ошибка вставки авто-ответа:", err)
    }
}

func fetchAvatarURL(bot *tgbotapi.BotAPI, userID int) string {
    cfg := tgbotapi.UserProfilePhotosConfig{UserID: userID, Limit: 1}
    photos, err := bot.GetUserProfilePhotos(cfg)
    if err != nil || photos.TotalCount == 0 {
        return ""
    }
    fileID := photos.Photos[0][0].FileID
    file, err := bot.GetFile(tgbotapi.FileConfig{FileID: fileID})
    if err != nil {
        return ""
    }
    return file.Link(bot.Token)
}

func saveChatUserToDB(db *sql.DB, botID int64, user *tgbotapi.User, avatarURL string) {
    query := `
        INSERT INTO tg_chat_users
          (bot_id, user_id, username, first_name, last_name, avatar_url, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
          username    = VALUES(username),
          first_name  = VALUES(first_name),
          last_name   = VALUES(last_name),
          avatar_url  = VALUES(avatar_url),
          updated_at  = NOW()
    `
    if _, err := db.Exec(
        query,
        botID,
        user.ID,
        user.UserName,
        user.FirstName,
        user.LastName,
        avatarURL,
    ); err != nil {
        log.Println("saveChatUserToDB error:", err)
    }
}
