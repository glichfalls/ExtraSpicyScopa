<?php

namespace App\Telegram;

use App\Service\ScopaGameService;
use BoShurik\TelegramBotBundle\Telegram\Command\CommandInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class InlineQueryHandler implements CommandInterface
{
    public function __construct(
        private readonly ScopaGameService $gameService,
    ) {
    }

    public function isApplicable(Update $update): bool
    {
        return $update->getInlineQuery() !== null;
    }

    public function execute(BotApi $api, Update $update): void
    {
        $inlineQuery = $update->getInlineQuery();
        $from = $inlineQuery->getFrom();

        $game = $this->gameService->findActiveGameForUser($from->getId());

        if ($game === null) {
            $api->call('answerInlineQuery', [
                'inline_query_id' => $inlineQuery->getId(),
                'results' => json_encode([]),
                'cache_time' => 1,
                'is_personal' => true,
            ]);
            return;
        }

        $player = $game->getPlayerByUser(
            $this->gameService->findOrCreateUser($from->getId(), $from->getFirstName(), $from->getUsername())
        );

        if ($player === null) {
            return;
        }

        // Check if it's this player's turn
        $isMyTurn = $game->getCurrentPlayer()->getPlayerIndex() === $player->getPlayerIndex();

        if (!$isMyTurn || $game->hasPendingCapture()) {
            $api->call('answerInlineQuery', [
                'inline_query_id' => $inlineQuery->getId(),
                'results' => json_encode([[
                    'type' => 'article',
                    'id' => 'wait',
                    'title' => $game->hasPendingCapture()
                        ? 'Choose a capture first!'
                        : 'Not your turn!',
                    'input_message_content' => [
                        'message_text' => $game->hasPendingCapture()
                            ? 'I need to choose a capture first...'
                            : "It's not my turn yet!",
                    ],
                ]]),
                'cache_time' => 1,
                'is_personal' => true,
            ]);
            return;
        }

        // Return hand cards as sticker results
        $results = [];
        $allCards = $this->gameService->getAllCards();

        foreach ($player->getHand() as $cardRef) {
            $card = $allCards[$cardRef] ?? null;
            if ($card === null || $card->getTelegramFileId() === null) {
                continue;
            }

            $results[] = [
                'type' => 'sticker',
                'id' => 'play_' . $cardRef,
                'sticker_file_id' => $card->getTelegramFileId(),
            ];
        }

        $api->call('answerInlineQuery', [
            'inline_query_id' => $inlineQuery->getId(),
            'results' => json_encode($results),
            'cache_time' => 0,
            'is_personal' => true,
        ]);
    }
}
