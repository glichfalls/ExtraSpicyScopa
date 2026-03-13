<?php

namespace App\Service;

use App\Entity\Card;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use TelegramBot\Api\BotApi;

class CardStickerService
{
    private const STICKER_SIZE = 512;
    private const STICKER_SET_PREFIX = 'scopa_deck';

    public function __construct(
        private readonly BotApi $botApi,
        private readonly CardRepository $cardRepository,
        private readonly EntityManagerInterface $em,
        private readonly string $botUsername,
        private readonly string $projectDir,
    ) {
    }

    public function getStickerSetName(): string
    {
        return self::STICKER_SET_PREFIX . '_by_' . $this->botUsername;
    }

    public function downloadCardImage(Card $card): string
    {
        $imageUrl = sprintf(
            'https://raw.githubusercontent.com/OMerkel/Scopa/master/data/cards/Napoletane/%d%s.jpg',
            $card->getValue(),
            $card->getSuit()->value[0],
        );

        $tmpDir = $this->projectDir . '/var/tmp/cards';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpPath = $tmpDir . '/' . $card->getRef() . '.jpg';
        $content = $this->fetchWithRetry($imageUrl);
        file_put_contents($tmpPath, $content);

        return $tmpPath;
    }

    public function processCardImage(string $imagePath): string
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($imagePath);

        $width = $image->width();
        $height = $image->height();

        // Scale so the longest side is 512px
        if ($width >= $height) {
            $image->scale(width: self::STICKER_SIZE);
        } else {
            $image->scale(height: self::STICKER_SIZE);
        }

        $pngPath = preg_replace('/\.\w+$/', '.png', $imagePath);
        $image->toPng()->save($pngPath);

        return $pngPath;
    }

    public function uploadStickerFile(string $pngPath, int $userId): string
    {
        $result = $this->botApi->call('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => new \CURLFile($pngPath, 'image/png'),
            'sticker_format' => 'static',
        ]);

        return $result['file_id'];
    }

    public function createStickerSet(int $userId, Card $firstCard, string $fileId): void
    {
        $this->botApi->call('createNewStickerSet', [
            'user_id' => $userId,
            'name' => $this->getStickerSetName(),
            'title' => 'Scopa - Carte Napoletane',
            'stickers' => json_encode([[
                'sticker' => $fileId,
                'format' => 'static',
                'emoji_list' => [$firstCard->getSuit()->emoji()],
                'keywords' => [$firstCard->getDisplayName()],
            ]]),
        ]);
    }

    public function addStickerToSet(int $userId, Card $card, string $fileId): void
    {
        $this->botApi->call('addStickerToSet', [
            'user_id' => $userId,
            'name' => $this->getStickerSetName(),
            'sticker' => json_encode([
                'sticker' => $fileId,
                'format' => 'static',
                'emoji_list' => [$card->getSuit()->emoji()],
                'keywords' => [$card->getDisplayName()],
            ]),
        ]);
    }

    public function stickerSetExists(): bool
    {
        try {
            $this->botApi->call('getStickerSet', [
                'name' => $this->getStickerSetName(),
            ]);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function deleteStickerSet(): void
    {
        try {
            $this->botApi->call('deleteStickerSet', [
                'name' => $this->getStickerSetName(),
            ]);
        } catch (\Exception) {
            // Already deleted or never existed
        }
    }

    /**
     * Process a single card: download, convert, upload, store file_id.
     * Returns the Telegram file_id.
     */
    public function processCard(Card $card, int $userId, bool $isFirst): string
    {
        $imagePath = $this->downloadCardImage($card);
        $pngPath = $this->processCardImage($imagePath);
        $fileId = $this->uploadStickerFile($pngPath, $userId);

        if ($isFirst) {
            $this->createStickerSet($userId, $card, $fileId);
            // Wait for Telegram to register the new set
            sleep(1);
        } else {
            // Verify set exists before adding
            if (!$this->stickerSetExists()) {
                throw new \RuntimeException(
                    'Sticker set "' . $this->getStickerSetName() . '" does not exist. createNewStickerSet may have failed.'
                );
            }
            $this->addStickerToSet($userId, $card, $fileId);
        }

        // Get the actual sticker file_id from the set (upload file_id != sticker file_id)
        $stickerFileId = $this->getLastStickerFileId();

        $card->setTelegramFileId($stickerFileId);
        $this->em->flush();

        // Clean up temp files
        @unlink($imagePath);
        @unlink($pngPath);

        return $stickerFileId;
    }

    /**
     * Fetch the existing sticker set from Telegram and populate file_ids in the DB.
     * Stickers are ordered: Denari 1-10, Coppe 1-10, Spade 1-10, Bastoni 1-10.
     *
     * @return int Number of cards synced
     */
    public function syncFromTelegram(): int
    {
        $result = $this->botApi->call('getStickerSet', [
            'name' => $this->getStickerSetName(),
        ]);

        $stickers = $result['stickers'];
        $cards = $this->cardRepository->findBy([], ['suit' => 'ASC', 'value' => 'ASC']);

        $synced = 0;
        foreach ($cards as $i => $card) {
            if (!isset($stickers[$i])) {
                break;
            }
            $card->setTelegramFileId($stickers[$i]['file_id']);
            $synced++;
        }

        $this->em->flush();

        return $synced;
    }

    private function getLastStickerFileId(): string
    {
        $result = $this->botApi->call('getStickerSet', [
            'name' => $this->getStickerSetName(),
        ]);

        $stickers = $result['stickers'];
        $lastSticker = end($stickers);

        return $lastSticker['file_id'];
    }

    private function fetchWithRetry(string $url, int $maxRetries = 5): string
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: ExtraSpicyScopaBot/1.0',
                'ignore_errors' => true,
            ],
        ]);

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $content = @file_get_contents($url, false, $context);

            if ($content !== false) {
                // Check for 429 in response headers
                $status = $http_response_header[0] ?? '';
                if (str_contains($status, '429')) {
                    $wait = (int) (2 ** $attempt) + 1;
                    sleep($wait);
                    continue;
                }
                if (str_contains($status, '200')) {
                    return $content;
                }
            }

            if ($attempt < $maxRetries) {
                $wait = (int) (2 ** $attempt) + 1;
                sleep($wait);
            }
        }

        throw new \RuntimeException("Failed to fetch $url after $maxRetries retries");
    }
}
