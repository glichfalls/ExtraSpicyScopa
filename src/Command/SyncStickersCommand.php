<?php

namespace App\Command;

use App\Repository\CardRepository;
use App\Service\CardStickerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stickers:sync',
    description: 'Sync card file_ids from an existing Telegram sticker set into the local database',
)]
class SyncStickersCommand extends Command
{
    public function __construct(
        private readonly CardStickerService $cardStickerService,
        private readonly CardRepository $cardRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $setName = $this->cardStickerService->getStickerSetName();
        $io->info("Syncing from sticker set: {$setName}");

        if (!$this->cardStickerService->stickerSetExists()) {
            $io->warning("Sticker set '{$setName}' does not exist yet. Skipping sync.");
            return Command::SUCCESS;
        }

        $this->cardRepository->seedAll();

        $synced = $this->cardStickerService->syncFromTelegram();

        $io->success("Synced {$synced} cards from Telegram sticker set.");

        return Command::SUCCESS;
    }
}
