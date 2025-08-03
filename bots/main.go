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
    // Московское время для Go
    loc, err := time.LoadLocation("Europe/Moscow")
    if err != nil {
        log.Fatal("Не удалось загрузить зону Europe/Moscow:", err)
    }
    time.Local = loc

    // DSN
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

    // московский часовой пояс в MySQL
    if _, err := db.Exec("SET time_zone = '+03:00'"); err != nil {
        log.Println("Не удалось выставить time_zone:", err)
    }

    bot, err := tgbotapi.NewBotAPI(botToken)
    if err != nil {
        log.Println("Неверный токен:", botToken, err)
        return
    }
    log.Printf("Бот @%s запущен\n", bot.Self.UserName)

    u := tgbotapi.NewUpdate(0)
    u.Timeout = 60
    updates, _ := bot.GetUpdatesChan(u)

    for update := range updates {
        if update.Message == nil {
            continue
        }
        user := update.Message.From
        chatID := update.Message.Chat.ID
        text := update.Message.Text

        // 1) Получаем ссылку на аватарку
        avatarURL := fetchAvatarURL(bot, user.ID)

        // 2) Сохраняем или обновляем запись в tg_chat_users
        saveChatUserToDB(db, user, avatarURL)

        // 3) Записываем сообщение с telegram_user_id
        ts := time.Now().Format("2006-01-02 15:04:05")
        _, err := db.Exec(
            "INSERT INTO chat_message(bot_token,chat_id,telegram_user_id,username,text,created_at) VALUES(?,?,?,?,?,?)",
            botToken, chatID, user.ID, user.UserName, text, ts,
        )
        if err != nil {
            log.Println("Ошибка вставки в chat_message:", err)
        }
    }
}

// fetchAvatarURL возвращает временный URL на последний профильный кадр пользователя
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

// saveChatUserToDB «upsert» записи в tg_chat_users
func saveChatUserToDB(db *sql.DB, user *tgbotapi.User, avatarURL string) {
    query := `
        INSERT INTO tg_chat_users
          (user_id, username, first_name, last_name, avatar_url, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
          username=VALUES(username),
          first_name=VALUES(first_name),
          last_name=VALUES(last_name),
          avatar_url=VALUES(avatar_url),
          updated_at=NOW()
    `
    if _, err := db.Exec(
        query,
        user.ID,
        user.UserName,
        user.FirstName,
        user.LastName,
        avatarURL,
    ); err != nil {
        log.Println("saveChatUserToDB error:", err)
    }
}
