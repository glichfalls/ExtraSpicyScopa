<?php

namespace App\Service;

use App\Entity\Card;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use TelegramBot\Api\BotApi;

class CardStickerService
{
    private const STICKER_SIZE = 512;
    private const STICKER_SET_PREFIX = 'scopa_deck';
    private const CARD_IMAGE_BASE = 'https://raw.githubusercontent.com/OMerkel/Scopa/master/data/cards/Napoletane/';

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

    public function stickerSetExists(): bool
    {
        try {
            $this->botApi->call('getStickerSet', ['name' => $this->getStickerSetName()]);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function deleteStickerSet(): void
    {
        try {
            $this->botApi->call('deleteStickerSet', ['name' => $this->getStickerSetName()]);
        } catch (\Exception) {
        }
    }

    public function createFullStickerSet(int $userId, ?callable $onProgress = null): void
    {
        $cards = $this->cardRepository->findBy([], ['suit' => 'ASC', 'value' => 'ASC']);
        $setName = $this->getStickerSetName();

        foreach ($cards as $i => $card) {
            $pngPath = $this->downloadAndConvert($card);
            $fileId = $this->uploadStickerFile($pngPath, $userId);

            if ($i === 0) {
                $this->botApi->call('createNewStickerSet', [
                    'user_id' => $userId,
                    'name' => $setName,
                    'title' => 'Scopa - Carte Napoletane',
                    'stickers' => json_encode([[
                        'sticker' => $fileId,
                        'format' => 'static',
                        'emoji_list' => [$card->getSuit()->emoji()],
                    ]]),
                ]);
            } else {
                $this->botApi->call('addStickerToSet', [
                    'user_id' => $userId,
                    'name' => $setName,
                    'sticker' => json_encode([
                        'sticker' => $fileId,
                        'format' => 'static',
                        'emoji_list' => [$card->getSuit()->emoji()],
                    ]),
                ]);
            }

            @unlink($pngPath);

            if ($onProgress) {
                $onProgress($card);
            }

            sleep(1);
        }

        $this->syncFromTelegram();
    }

    public function syncFromTelegram(): int
    {
        $result = $this->botApi->call('getStickerSet', ['name' => $this->getStickerSetName()]);
        $stickers = is_array($result) ? $result['stickers'] : $result->stickers;
        $cards = $this->cardRepository->findBy([], ['suit' => 'ASC', 'value' => 'ASC']);

        $synced = 0;
        foreach ($cards as $i => $card) {
            if (!isset($stickers[$i])) {
                break;
            }
            $sticker = (array) $stickers[$i];
            $card->setTelegramFileId($sticker['file_id']);
            $synced++;
        }

        $this->em->flush();
        return $synced;
    }

    private function downloadAndConvert(Card $card): string
    {
        $url = self::CARD_IMAGE_BASE . $card->getValue() . $card->getSuit()->value[0] . '.jpg';

        $tmpDir = $this->projectDir . '/var/tmp/cards';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $pngPath = $tmpDir . '/' . $card->getRef() . '.png';

        $content = file_get_contents($url, false, stream_context_create([
            'http' => ['header' => 'User-Agent: ExtraSpicyScopaBot/1.0'],
        ]));

        if ($content === false) {
            throw new \RuntimeException("Failed to download $url");
        }

        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($content);

        if ($image->width() >= $image->height()) {
            $image->scale(width: self::STICKER_SIZE);
        } else {
            $image->scale(height: self::STICKER_SIZE);
        }

        $image->toPng()->save($pngPath);

        return $pngPath;
    }

    private function uploadStickerFile(string $pngPath, int $userId): string
    {
        $result = $this->botApi->call('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => new \CURLFile($pngPath, 'image/png'),
            'sticker_format' => 'static',
        ]);

        $result = (array) $result;
        return $result['file_id'];
    }
}
