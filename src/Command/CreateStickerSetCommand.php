<?php

namespace App\Command;

use App\Repository\CardRepository;
use App\Service\CardStickerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stickers:create',
    description: 'Download card images from Wikimedia and create the Telegram sticker set',
)]
class CreateStickerSetCommand extends Command
{
    public function __construct(
        private readonly CardStickerService $cardStickerService,
        private readonly CardRepository $cardRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'Telegram user ID to own the sticker set')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete existing sticker set and recreate')
            ->addOption('resume', 'r', InputOption::VALUE_NONE, 'Resume: skip cards that already have a file_id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = (int) $input->getArgument('user-id');

        $io->title('Scopa Sticker Set Creator');

        // Seed cards in database
        $io->section('Seeding cards...');
        $this->cardRepository->seedAll();
        $io->info('40 cards seeded.');

        $resume = $input->getOption('resume');
        $setExists = $this->cardStickerService->stickerSetExists();

        // Check if sticker set already exists
        if ($setExists && !$resume) {
            if ($input->getOption('force')) {
                $io->warning('Deleting existing sticker set...');
                $this->cardStickerService->deleteStickerSet();
                $setExists = false;
            } else {
                $io->error('Sticker set already exists. Use --force to recreate or --resume to continue.');
                return Command::FAILURE;
            }
        }

        $stickerSetName = $this->cardStickerService->getStickerSetName();
        $io->info("Sticker set: {$stickerSetName}");

        // Process all cards
        $cards = $this->cardRepository->findBy([], ['suit' => 'ASC', 'value' => 'ASC']);

        $remaining = $resume
            ? array_filter($cards, fn($c) => $c->getTelegramFileId() === null)
            : $cards;

        if ($resume) {
            $skipped = count($cards) - count($remaining);
            $io->info("Resuming: skipping {$skipped} already uploaded cards.");
        }

        $io->section('Processing ' . count($remaining) . ' cards...');
        $io->progressStart(count($remaining));

        $isFirst = !$setExists;
        foreach ($remaining as $card) {
            try {
                $this->cardStickerService->processCard($card, $userId, $isFirst);
                $isFirst = false;
                $io->progressAdvance();
            } catch (\Exception $e) {
                $io->newLine();
                $io->error(sprintf('Failed on %s: %s', $card->getDisplayName(), $e->getMessage()));
                return Command::FAILURE;
            }

            // Telegram rate limit
            usleep(500_000);
        }

        $io->progressFinish();
        $io->success(sprintf(
            "Sticker set created: https://t.me/addstickers/%s",
            $stickerSetName
        ));

        return Command::SUCCESS;
    }
}
