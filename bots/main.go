package main

import (
    "database/sql"
    "fmt"
    "io"
    "log"
    "mime"
    "net/http"
    "os"
    "path/filepath"
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

        // текст может быть в Caption у фото/видео
        text := update.Message.Text
        if text == "" && update.Message.Caption != "" {
            text = update.Message.Caption
        }

        // Upsert пользователя
        avatarURL := fetchAvatarURL(bot, user.ID)
        saveChatUserToDB(db, botID, user, avatarURL)

        // 2.1) Сохраняем запись в chat_message и получаем её id
        res, err := db.Exec(
            `INSERT INTO chat_message
               (bot_token, chat_id, telegram_user_id, username, text, created_at, updated_at)
             VALUES(?, ?, ?, ?, ?, NOW(), NOW())`,
            botToken,
            chatID,
            user.ID,
            user.UserName,
            text,
        )
        if err != nil {
            log.Println("Ошибка вставки сообщения:", err)
            continue
        }
        messageID, _ := res.LastInsertId()
        log.Printf("chat_message saved: id=%d chat=%d user=%d\n", messageID, chatID, user.ID)

        // 2.2) Обрабатываем вложения и пишем в chat_message_files

        // PHOTO: у твоей версии tgbotapi это *([]PhotoSize), поэтому разыменовываем
        if update.Message.Photo != nil && len(*update.Message.Photo) > 0 {
            ph := *update.Message.Photo
            p := ph[len(ph)-1] // самый большой
            rel, mimeType, size, err := downloadTelegramFile(bot, p.FileID, fmt.Sprintf("photo_%d.jpg", time.Now().UnixNano()))
            if err == nil {
                insertMessageFile(db, messageID, "photo.jpg", rel, mimeType, size)
            } else {
                log.Println("photo download error:", err)
            }
        }

        // DOCUMENT (в т.ч. gif приходит как document)
        if doc := update.Message.Document; doc != nil {
            rel, mimeType, size, err := downloadTelegramFile(bot, doc.FileID, safeFileName(doc.FileName, "document"))
            if err == nil {
                insertMessageFile(db, messageID, emptyIfTooLong(doc.FileName, "document"), rel, mimeType, size)
            } else {
                log.Println("document download error:", err)
            }
        }

        // ANIMATION (gif/mp4)
        if anim := update.Message.Animation; anim != nil {
            rel, mimeType, size, err := downloadTelegramFile(bot, anim.FileID, fmt.Sprintf("animation_%d.mp4", time.Now().UnixNano()))
            if err == nil {
                insertMessageFile(db, messageID, "animation.mp4", rel, mimeType, size)
            } else {
                log.Println("animation download error:", err)
            }
        }

        // VIDEO
        if v := update.Message.Video; v != nil {
            rel, mimeType, size, err := downloadTelegramFile(bot, v.FileID, fmt.Sprintf("video_%d.mp4", time.Now().UnixNano()))
            if err == nil {
                insertMessageFile(db, messageID, "video.mp4", rel, mimeType, size)
            } else {
                log.Println("video download error:", err)
            }
        }

        // AUDIO — у старых версий нет FileName, берём Title (или дефолт)
        if a := update.Message.Audio; a != nil {
            base := a.Title
            if base == "" {
                base = "audio"
            }
            rel, mimeType, size, err := downloadTelegramFile(bot, a.FileID, safeFileName(base, "audio"))
            if err == nil {
                insertMessageFile(db, messageID, emptyIfTooLong(base, "audio"), rel, mimeType, size)
            } else {
                log.Println("audio download error:", err)
            }
        }

        // VOICE (обычно .ogg/opus)
        if v := update.Message.Voice; v != nil {
            rel, mimeType, size, err := downloadTelegramFile(bot, v.FileID, fmt.Sprintf("voice_%d.ogg", time.Now().UnixNano()))
            if err == nil {
                insertMessageFile(db, messageID, "voice.ogg", rel, mimeType, size)
            } else {
                log.Println("voice download error:", err)
            }
        }

        // STICKER
        if st := update.Message.Sticker; st != nil {
            rel, mimeType, size, err := downloadTelegramFile(bot, st.FileID, "sticker")
            if err == nil {
                insertMessageFile(db, messageID, "sticker", rel, mimeType, size)
            } else {
                log.Println("sticker download error:", err)
            }
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

// ======== ВАЖНОЕ: куда сохраняем файлы ========

// publicStorageRoot возвращает абсолютный путь к Laravel storage/app/public.
// Сначала берём из переменной окружения PUBLIC_STORAGE_ABS (если задана),
// иначе вычисляем относительно расположения исполняемого файла (../storage/app/public).
func publicStorageRoot() string {
    if v := os.Getenv("PUBLIC_STORAGE_ABS"); v != "" {
        return v
    }
    exe, err := os.Executable()
    if err != nil {
        // запасной вариант: текущая рабочая директория
        wd, _ := os.Getwd()
        return filepath.Join(wd, "..", "storage", "app", "public")
    }
    // если бот находится в ...\chopchop.local\bots\bot.exe
    // то нужно подняться на уровень выше (..\) -> storage\app\public
    base := filepath.Dir(exe)
    return filepath.Join(base, "..", "storage", "app", "public")
}

// safeFileName подрежет слишком длинные имена и подставит дефолт при пустом
func safeFileName(name, def string) string {
    if name == "" {
        return def
    }
    if len(name) > 120 {
        return name[len(name)-120:]
    }
    return name
}

// emptyIfTooLong — если имя пустое, подставит def; если слишком длинное — обрежет
func emptyIfTooLong(name, def string) string {
    if name == "" {
        return def
    }
    if len(name) > 255 {
        return name[len(name)-255:]
    }
    return name
}

// downloadTelegramFile качает файл по fileID в public storage и возвращает относительный путь, mime и размер.
// ВНИМАНИЕ: сохраняем в storage/app/public/telegram/YYYY/MM/... проекта (не в bots/).
func downloadTelegramFile(bot *tgbotapi.BotAPI, fileID, suggestedName string) (string, string, int64, error) {
    file, err := bot.GetFile(tgbotapi.FileConfig{FileID: fileID})
    if err != nil {
        return "", "", 0, err
    }

    url := file.Link(bot.Token)

    now := time.Now()
    baseDir := filepath.Join(publicStorageRoot(), "telegram", now.Format("2006"), now.Format("01"))
    if err := os.MkdirAll(baseDir, 0o755); err != nil {
        return "", "", 0, err
    }

    // Имя: если у suggestedName нет расширения, добавим из file_path
    ext := filepath.Ext(file.FilePath)
    name := safeFileName(suggestedName, "file")
    if filepath.Ext(name) == "" && ext != "" {
        name += ext
    }

    dstPath := filepath.Join(baseDir, name)
    out, err := os.Create(dstPath)
    if err != nil {
        return "", "", 0, err
    }
    defer out.Close()

    resp, err := http.Get(url)
    if err != nil {
        return "", "", 0, err
    }
    defer resp.Body.Close()

    n, err := io.Copy(out, resp.Body)
    if err != nil {
        return "", "", 0, err
    }

    // MIME
    mtype := resp.Header.Get("Content-Type")
    if mtype == "" {
        m := mime.TypeByExtension(filepath.Ext(dstPath))
        if m == "" {
            m = "application/octet-stream"
        }
        mtype = m
    }

    // относительный путь для БД (Laravel: Storage::url("telegram/...") → /storage/telegram/...)
    rel := filepath.ToSlash(filepath.Join("telegram", now.Format("2006"), now.Format("01"), name))

    return rel, mtype, n, nil
}

// insertMessageFile создаёт строку в chat_message_files
func insertMessageFile(db *sql.DB, messageID int64, fileName, relPath, mimeType string, size int64) {
    _, err := db.Exec(
        `INSERT INTO chat_message_files
           (chat_message_id, file_name, file_path, mime_type, size, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())`,
        messageID, fileName, relPath, mimeType, size,
    )
    if err != nil {
        log.Println("insertMessageFile error:", err)
    }
}
