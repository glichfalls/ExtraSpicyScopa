<?php

namespace App\Telegram;

use App\Repository\UserRepository;
use App\Service\OpenAiImageService;
use App\Service\StickerService;
use App\Service\TelegramService;
use Psr\Log\LoggerInterface;

class InlineQueryHandler
{
    public function __construct(
        private readonly OpenAiImageService $openAiImageService,
        private readonly StickerService $stickerService,
        private readonly TelegramService $telegramService,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(array $inlineQuery): void
    {
        $queryId = $inlineQuery['id'];
        $query = trim($inlineQuery['query'] ?? '');
        $fromUser = $inlineQuery['from'];

        if (empty($query)) {
            $this->telegramService->answerInlineQuery($queryId, [], 300);
            return;
        }

        [$emoji, $description] = $this->parseQuery($query);

        if (empty($description)) {
            $this->telegramService->answerInlineQuery($queryId, [], 300);
            return;
        }

        try {
            $user = $this->userRepository->findOrCreateByTelegramData(
                $fromUser['id'],
                $fromUser['first_name'] ?? 'User',
                $fromUser['username'] ?? null
            );

            $imageData = $this->openAiImageService->generateImage($description);
            $pngData = $this->stickerService->convertToPng($imageData);

            $this->stickerService->addStickerToPack($user, $pngData, $emoji);

            $packName = $user->getStickerPackName() ?? $this->stickerService->generatePackName($user);
            $stickerSet = $this->telegramService->getStickerSet($packName);

            if ($stickerSet === null || empty($stickerSet['stickers'])) {
                throw new \RuntimeException('Could not retrieve sticker set');
            }

            $lastSticker = end($stickerSet['stickers']);
            $fileId = $lastSticker['file_id'];

            $results = [
                [
                    'type' => 'sticker',
                    'id' => uniqid('sticker_', true),
                    'sticker_file_id' => $fileId,
                ],
            ];

            $this->telegramService->answerInlineQuery($queryId, $results, 0);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to handle inline query', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            $this->telegramService->answerInlineQuery($queryId, [], 0);
        }
    }

    private function parseQuery(string $query): array
    {
        $emoji = $this->extractEmoji($query);
        $description = $this->removeEmoji($query);

        if ($emoji === null) {
            $emoji = "\u{1F3A8}";
        }

        return [$emoji, trim($description)];
    }

    private function extractEmoji(string $text): ?string
    {
        $emojiPattern = '/[\x{1F600}-\x{1F64F}' .
            '\x{1F300}-\x{1F5FF}' .
            '\x{1F680}-\x{1F6FF}' .
            '\x{1F1E0}-\x{1F1FF}' .
            '\x{2600}-\x{26FF}' .
            '\x{2700}-\x{27BF}' .
            '\x{FE00}-\x{FE0F}' .
            '\x{1F900}-\x{1F9FF}' .
            '\x{1FA00}-\x{1FA6F}' .
            '\x{1FA70}-\x{1FAFF}' .
            '\x{231A}-\x{231B}' .
            '\x{23E9}-\x{23F3}' .
            '\x{23F8}-\x{23FA}' .
            '\x{25AA}-\x{25AB}' .
            '\x{25B6}' .
            '\x{25C0}' .
            '\x{25FB}-\x{25FE}' .
            '\x{2614}-\x{2615}' .
            '\x{2648}-\x{2653}' .
            '\x{267F}' .
            '\x{2693}' .
            '\x{26A1}' .
            '\x{26AA}-\x{26AB}' .
            '\x{26BD}-\x{26BE}' .
            '\x{26C4}-\x{26C5}' .
            '\x{26CE}' .
            '\x{26D4}' .
            '\x{26EA}' .
            '\x{26F2}-\x{26F3}' .
            '\x{26F5}' .
            '\x{26FA}' .
            '\x{26FD}' .
            '\x{2702}' .
            '\x{2705}' .
            '\x{2708}-\x{270D}' .
            '\x{270F}' .
            '\x{2712}' .
            '\x{2714}' .
            '\x{2716}' .
            '\x{271D}' .
            '\x{2721}' .
            '\x{2728}' .
            '\x{2733}-\x{2734}' .
            '\x{2744}' .
            '\x{2747}' .
            '\x{274C}' .
            '\x{274E}' .
            '\x{2753}-\x{2755}' .
            '\x{2757}' .
            '\x{2763}-\x{2764}' .
            '\x{2795}-\x{2797}' .
            '\x{27A1}' .
            '\x{27B0}' .
            '\x{27BF}' .
            '\x{2934}-\x{2935}' .
            '\x{2B05}-\x{2B07}' .
            '\x{2B1B}-\x{2B1C}' .
            '\x{2B50}' .
            '\x{2B55}' .
            '\x{3030}' .
            '\x{303D}' .
            '\x{3297}' .
            '\x{3299}]/u';

        if (preg_match($emojiPattern, $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function removeEmoji(string $text): string
    {
        $emojiPattern = '/[\x{1F600}-\x{1F64F}' .
            '\x{1F300}-\x{1F5FF}' .
            '\x{1F680}-\x{1F6FF}' .
            '\x{1F1E0}-\x{1F1FF}' .
            '\x{2600}-\x{26FF}' .
            '\x{2700}-\x{27BF}' .
            '\x{FE00}-\x{FE0F}' .
            '\x{1F900}-\x{1F9FF}' .
            '\x{1FA00}-\x{1FA6F}' .
            '\x{1FA70}-\x{1FAFF}' .
            '\x{231A}-\x{231B}' .
            '\x{23E9}-\x{23F3}' .
            '\x{23F8}-\x{23FA}' .
            '\x{25AA}-\x{25AB}' .
            '\x{25B6}' .
            '\x{25C0}' .
            '\x{25FB}-\x{25FE}' .
            '\x{2614}-\x{2615}' .
            '\x{2648}-\x{2653}' .
            '\x{267F}' .
            '\x{2693}' .
            '\x{26A1}' .
            '\x{26AA}-\x{26AB}' .
            '\x{26BD}-\x{26BE}' .
            '\x{26C4}-\x{26C5}' .
            '\x{26CE}' .
            '\x{26D4}' .
            '\x{26EA}' .
            '\x{26F2}-\x{26F3}' .
            '\x{26F5}' .
            '\x{26FA}' .
            '\x{26FD}' .
            '\x{2702}' .
            '\x{2705}' .
            '\x{2708}-\x{270D}' .
            '\x{270F}' .
            '\x{2712}' .
            '\x{2714}' .
            '\x{2716}' .
            '\x{271D}' .
            '\x{2721}' .
            '\x{2728}' .
            '\x{2733}-\x{2734}' .
            '\x{2744}' .
            '\x{2747}' .
            '\x{274C}' .
            '\x{274E}' .
            '\x{2753}-\x{2755}' .
            '\x{2757}' .
            '\x{2763}-\x{2764}' .
            '\x{2795}-\x{2797}' .
            '\x{27A1}' .
            '\x{27B0}' .
            '\x{27BF}' .
            '\x{2934}-\x{2935}' .
            '\x{2B05}-\x{2B07}' .
            '\x{2B1B}-\x{2B1C}' .
            '\x{2B50}' .
            '\x{2B55}' .
            '\x{3030}' .
            '\x{303D}' .
            '\x{3297}' .
            '\x{3299}]/u';

        return preg_replace($emojiPattern, '', $text);
    }
}
