<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramMediaSaver {
    public function saveFromUpdate(array $update): array {
        $attachments = [];

        $msg = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$msg) return $attachments;

        // PHOTO (берем самый большой размер)
        if (!empty($msg['photo'])) {
            $photo = collect($msg['photo'])->sortByDesc('file_size')->first();
            $attachments[] = $this->downloadFile($photo['file_id'], 'image');
        }

        // DOCUMENT (в т.ч. gif как document/animation)
        if (!empty($msg['document'])) {
            $mime = $msg['document']['mime_type'] ?? null;
            $type = str_starts_with($mime, 'image/') ? 'image' : 'document';
            $attachments[] = $this->downloadFile($msg['document']['file_id'], $type, $mime);
        }

        // ANIMATION (gif/mp4)
        if (!empty($msg['animation'])) {
            $attachments[] = $this->downloadFile($msg['animation']['file_id'], 'animation');
        }

        // VIDEO
        if (!empty($msg['video'])) {
            $attachments[] = $this->downloadFile($msg['video']['file_id'], 'video');
        }

        // AUDIO
        if (!empty($msg['audio'])) {
            $attachments[] = $this->downloadFile($msg['audio']['file_id'], 'audio');
        }

        // VOICE
        if (!empty($msg['voice'])) {
            $attachments[] = $this->downloadFile($msg['voice']['file_id'], 'voice');
        }

        // STICKER
        if (!empty($msg['sticker'])) {
            $attachments[] = $this->downloadFile($msg['sticker']['file_id'], 'sticker', 'image/webp');
        }

        return array_values(array_filter($attachments));
    }

    protected function downloadFile(string $fileId, string $type, ?string $mime = null): ?array {
        $bot = config('services.telegram.bot_token');
        $file = Http::get("https://api.telegram.org/bot{$bot}/getFile", ['file_id' => $fileId])
            ->json('result.file_path');
        if (!$file) return null;

        $stream = Http::withOptions(['stream' => true])
            ->get("https://api.telegram.org/file/bot{$bot}/{$file}");
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $path = "chat/telegram/".date('Y/m/d')."/".uniqid().".{$ext}";
        Storage::disk('public')->put($path, $stream->body());

        return [
            'type' => $type,
            'mime' => $mime,
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ];
    }
}
