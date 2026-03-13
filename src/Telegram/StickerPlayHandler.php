<?php

namespace App\Telegram;

use App\Entity\Game;
use App\Entity\Player;
use App\Service\GameMessageService;
use App\Service\ScopaGameService;
use BoShurik\TelegramBotBundle\Telegram\Command\CommandInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class StickerPlayHandler implements CommandInterface
{
    public function __construct(
        private readonly ScopaGameService $gameService,
        private readonly GameMessageService $messageService,
    ) {
    }

    public function isApplicable(Update $update): bool
    {
        $message = $update->getMessage();
        if ($message === null) {
            return false;
        }

        return $message->getSticker() !== null;
    }

    public function execute(BotApi $api, Update $update): void
    {
        $message = $update->getMessage();
        $sticker = $message->getSticker();
        $chatId = $message->getChat()->getId();
        $from = $message->getFrom();

        // Look up the card by sticker file_id
        $card = $this->gameService->getCardByFileId($sticker->getFileId());
        if ($card === null) {
            return; // Not one of our cards, ignore
        }

        // Find active game in this chat
        $game = $this->gameService->findActiveGameForChat($chatId);
        if ($game === null) {
            return;
        }

        // Find the player
        $user = $this->gameService->findOrCreateUser(
            $from->getId(),
            $from->getFirstName(),
            $from->getUsername(),
        );
        $player = $game->getPlayerByUser($user);
        if ($player === null) {
            return;
        }

        // Check turn
        if ($game->getCurrentPlayer()->getPlayerIndex() !== $player->getPlayerIndex()) {
            $this->messageService->sendNotYourTurn($chatId, $message->getMessageId());
            return;
        }

        // Check pending capture (must resolve that first)
        if ($game->hasPendingCapture()) {
            return;
        }

        $cardRef = $card->getRef();

        // Check card is in hand
        if (!$player->hasInHand($cardRef)) {
            return;
        }

        // Play the card
        $result = $this->gameService->playCard($game, $player, $cardRef);

        // Handle capture choice
        if ($result['needsCaptureChoice']) {
            $this->messageService->sendCaptureChoice($game, $cardRef, $result['captureOptions']);
            return;
        }

        $this->handlePlayResult($game, $player, $cardRef, $result);
    }

    private function handlePlayResult(Game $game, Player $player, string $cardRef, array $result): void
    {
        $this->messageService->sendPlayResult($game, $player, $cardRef, $result);

        if ($result['roundOver']) {
            $this->messageService->sendRoundScore($game, $result['roundScores']);

            if ($result['gameOver']) {
                $this->messageService->sendGameOver($game);
                return;
            }

            $this->messageService->sendNewRound($game);
            // New round = new table message
            $game->setTableMessageId(null);
        }

        if ($result['dealt'] && !$result['roundOver']) {
            $this->messageService->sendDealt($game);
        }

        $this->messageService->sendTableState($game);
    }
}
