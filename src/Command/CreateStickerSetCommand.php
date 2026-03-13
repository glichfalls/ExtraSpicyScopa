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
    description: 'Download card images and create the Telegram sticker set',
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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete existing sticker set and recreate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = (int) $input->getArgument('user-id');

        $this->cardRepository->seedAll();

        $setName = $this->cardStickerService->getStickerSetName();
        $io->info("Sticker set: $setName");

        if ($this->cardStickerService->stickerSetExists()) {
            if (!$input->getOption('force')) {
                $io->error('Sticker set already exists. Use --force to recreate.');
                return Command::FAILURE;
            }
            $io->warning('Deleting existing sticker set...');
            $this->cardStickerService->deleteStickerSet();
            sleep(2);
        }

        $io->info('Creating sticker set with 40 cards...');
        $io->progressStart(40);

        try {
            $this->cardStickerService->createFullStickerSet($userId, function () use ($io) {
                $io->progressAdvance();
            });
        } catch (\Exception $e) {
            $io->progressFinish();
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->progressFinish();
        $io->success("Done! https://t.me/addstickers/$setName");

        return Command::SUCCESS;
    }
}
