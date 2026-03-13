<?php

namespace App\Telegram;

use App\Entity\Game;
use App\Entity\Player;
use App\Service\GameMessageService;
use App\Service\ScopaGameService;
use BoShurik\TelegramBotBundle\Telegram\Command\CommandInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class GameCallbackHandler implements CommandInterface
{
    public function __construct(
        private readonly ScopaGameService $gameService,
        private readonly GameMessageService $messageService,
    ) {
    }

    public function isApplicable(Update $update): bool
    {
        $cb = $update->getCallbackQuery();
        if ($cb === null) {
            return false;
        }

        $data = $cb->getData();
        return str_starts_with($data, 'join:') || str_starts_with($data, 'cap:');
    }

    public function execute(BotApi $api, Update $update): void
    {
        $cb = $update->getCallbackQuery();
        $data = $cb->getData();
        $answered = false;

        if (str_starts_with($data, 'join:')) {
            $answered = $this->handleJoin($api, $cb);
        } elseif (str_starts_with($data, 'cap:')) {
            $this->handleCapture($api, $cb);
        }

        if (!$answered) {
            try {
                $api->call('answerCallbackQuery', [
                    'callback_query_id' => $cb->getId(),
                ]);
            } catch (\Exception) {
            }
        }
    }

    private function handleJoin(BotApi $api, $cb): bool
    {
        $gameId = (int) substr($cb->getData(), 5);
        $from = $cb->getFrom();

        $game = $this->gameService->findActiveGameForChat(
            $cb->getMessage()->getChat()->getId()
        );

        if ($game === null || $game->getId() !== $gameId) {
            return false;
        }

        $user = $this->gameService->findOrCreateUser(
            $from->getId(),
            $from->getFirstName(),
            $from->getUsername(),
        );

        try {
            $this->gameService->joinGame($game, $user);
        } catch (\RuntimeException $e) {
            $api->call('answerCallbackQuery', [
                'callback_query_id' => $cb->getId(),
                'text' => $e->getMessage(),
                'show_alert' => true,
            ]);
            return true;
        }

        // Remove the join button
        try {
            $api->call('editMessageText', [
                'chat_id' => $cb->getMessage()->getChat()->getId(),
                'message_id' => $cb->getMessage()->getMessageId(),
                'text' => sprintf(
                    "\u{1F3B4} *Scopa!*\n\n%s vs %s \u{2014} Game on!",
                    $game->getPlayer(0)->getDisplayName(),
                    $game->getPlayer(1)->getDisplayName(),
                ),
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception) {
        }

        // Send initial table state
        $this->messageService->sendTableState($game);

        return false;
    }

    private function handleCapture(BotApi $api, $cb): void
    {
        $parts = explode(':', $cb->getData());
        if (count($parts) !== 3) {
            return;
        }

        $gameId = (int) $parts[1];
        $captureIndex = (int) $parts[2];
        $from = $cb->getFrom();

        $game = $this->gameService->findActiveGameForChat(
            $cb->getMessage()->getChat()->getId()
        );

        if ($game === null || $game->getId() !== $gameId || !$game->hasPendingCapture()) {
            return;
        }

        // Verify it's the correct player
        $currentPlayer = $game->getCurrentPlayer();
        if ($currentPlayer->getUser()->getTelegramId() !== $from->getId()) {
            return;
        }

        $captures = $game->getPendingCaptures();
        if ($captureIndex < 0 || $captureIndex >= count($captures)) {
            return;
        }

        $result = $this->gameService->resolveCaptureChoice($game, $captureIndex);

        // Remove the choice message
        try {
            $api->call('deleteMessage', [
                'chat_id' => $cb->getMessage()->getChat()->getId(),
                'message_id' => $cb->getMessage()->getMessageId(),
            ]);
        } catch (\Exception) {
        }

        $this->handlePlayResult($game, $currentPlayer, $result);
    }

    private function handlePlayResult(Game $game, $player, array $result): void
    {
        $this->messageService->sendPlayResult($game, $player, '', $result);

        if ($result['roundOver']) {
            $this->messageService->sendRoundScore($game, $result['roundScores']);

            if ($result['gameOver']) {
                $this->messageService->sendGameOver($game);
                return;
            }

            $this->messageService->sendNewRound($game);
            // Send new table state for the new round
            $game->setTableMessageId(null);
        }

        if ($result['dealt'] && !$result['roundOver']) {
            $this->messageService->sendDealt($game);
        }

        $this->messageService->sendTableState($game);
    }
}
