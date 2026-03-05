<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function createStickerSet(
        int $userId,
        string $name,
        string $title,
        string $pngSticker,
        string $emojis
    ): bool {
        $response = $this->httpClient->request('POST', 'createNewStickerSet', [
            'body' => [
                'user_id' => $userId,
                'name' => $name,
                'title' => $title,
                'stickers' => json_encode([
                    [
                        'sticker' => 'attach://sticker',
                        'format' => 'static',
                        'emoji_list' => [$emojis],
                    ],
                ]),
            ],
            'extra' => [
                'files' => [
                    'sticker' => $pngSticker,
                ],
            ],
        ]);

        $data = $response->toArray(false);
        return $data['ok'] ?? false;
    }

    public function addStickerToSet(
        int $userId,
        string $name,
        string $pngSticker,
        string $emojis
    ): bool {
        $response = $this->httpClient->request('POST', 'addStickerToSet', [
            'body' => [
                'user_id' => $userId,
                'name' => $name,
                'sticker' => json_encode([
                    'sticker' => 'attach://sticker',
                    'format' => 'static',
                    'emoji_list' => [$emojis],
                ]),
            ],
            'extra' => [
                'files' => [
                    'sticker' => $pngSticker,
                ],
            ],
        ]);

        $data = $response->toArray(false);
        return $data['ok'] ?? false;
    }

    public function getStickerSet(string $name): ?array
    {
        $response = $this->httpClient->request('GET', 'getStickerSet', [
            'query' => ['name' => $name],
        ]);

        $data = $response->toArray(false);

        if (!($data['ok'] ?? false)) {
            return null;
        }

        return $data['result'] ?? null;
    }

    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        int $cacheTime = 0
    ): bool {
        $response = $this->httpClient->request('POST', 'answerInlineQuery', [
            'json' => [
                'inline_query_id' => $inlineQueryId,
                'results' => $results,
                'cache_time' => $cacheTime,
            ],
        ]);

        $data = $response->toArray(false);
        return $data['ok'] ?? false;
    }

    public function uploadStickerFile(int $userId, string $pngSticker): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sticker_');
        file_put_contents($tempFile, $pngSticker);

        try {
            $response = $this->httpClient->request('POST', 'uploadStickerFile', [
                'body' => [
                    'user_id' => $userId,
                    'sticker' => new \CURLFile($tempFile, 'image/png', 'sticker.png'),
                    'sticker_format' => 'static',
                ],
            ]);

            $data = $response->toArray(false);

            if (!($data['ok'] ?? false)) {
                return null;
            }

            return $data['result']['file_id'] ?? null;
        } finally {
            @unlink($tempFile);
        }
    }

    public function sendMessage(int $chatId, string $text): bool
    {
        $response = $this->httpClient->request('POST', 'sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
            ],
        ]);

        $data = $response->toArray(false);
        return $data['ok'] ?? false;
    }
}
