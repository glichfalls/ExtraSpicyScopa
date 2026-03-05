<?php

namespace App\Service;

use App\Entity\StickerPack;
use App\Entity\User;
use App\Repository\StickerPackRepository;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class StickerService
{
    private ImageManager $imageManager;

    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly StickerPackRepository $stickerPackRepository,
        private readonly string $botUsername,
    ) {
        $this->imageManager = new ImageManager(new Driver());
    }

    public function convertToWebP(string $imageData): string
    {
        $image = $this->imageManager->read($imageData);

        $width = $image->width();
        $height = $image->height();

        if ($width > $height) {
            $image->resize(512, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        } else {
            $image->resize(null, 512, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        return $image->toWebp(90)->toString();
    }

    public function convertToPng(string $imageData): string
    {
        $image = $this->imageManager->read($imageData);

        $width = $image->width();
        $height = $image->height();

        if ($width > $height) {
            $image->scale(width: 512);
        } else {
            $image->scale(height: 512);
        }

        return $image->toPng()->toString();
    }

    public function getOrCreateStickerPack(User $user, string $emoji, string $pngData): StickerPack
    {
        $existingPack = $this->stickerPackRepository->findByUser($user);

        if ($existingPack !== null) {
            return $existingPack;
        }

        $packName = $this->generatePackName($user);
        $packTitle = sprintf("%s's AI Stickers", $user->getFirstName());

        $fileId = $this->telegramService->uploadStickerFile($user->getTelegramId(), $pngData);

        if ($fileId === null) {
            throw new \RuntimeException('Failed to upload sticker file');
        }

        $success = $this->telegramService->createStickerSet(
            $user->getTelegramId(),
            $packName,
            $packTitle,
            $fileId,
            $emoji
        );

        if (!$success) {
            throw new \RuntimeException('Failed to create sticker pack');
        }

        $stickerPack = new StickerPack();
        $stickerPack->setUser($user);
        $stickerPack->setName($packName);
        $stickerPack->setTitle($packTitle);
        $stickerPack->setStickerCount(1);

        $this->stickerPackRepository->save($stickerPack);

        $user->setStickerPackName($packName);

        return $stickerPack;
    }

    public function addStickerToPack(User $user, string $pngData, string $emoji): void
    {
        $pack = $this->stickerPackRepository->findByUser($user);

        if ($pack === null) {
            $this->getOrCreateStickerPack($user, $emoji, $pngData);
            return;
        }

        $fileId = $this->telegramService->uploadStickerFile($user->getTelegramId(), $pngData);

        if ($fileId === null) {
            throw new \RuntimeException('Failed to upload sticker file');
        }

        $success = $this->telegramService->addStickerToSet(
            $user->getTelegramId(),
            $pack->getName(),
            $fileId,
            $emoji
        );

        if (!$success) {
            throw new \RuntimeException('Failed to add sticker to pack');
        }

        $pack->incrementStickerCount();
        $this->stickerPackRepository->save($pack);
    }

    public function generatePackName(User $user): string
    {
        return sprintf('stickers_by_%d_by_%s', $user->getTelegramId(), $this->botUsername);
    }

    public function getStickerPackUrl(User $user): string
    {
        $packName = $user->getStickerPackName() ?? $this->generatePackName($user);
        return sprintf('https://t.me/addstickers/%s', $packName);
    }
}
