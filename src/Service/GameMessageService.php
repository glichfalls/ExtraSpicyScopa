<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\Game;
use App\Entity\Player;
use TelegramBot\Api\BotApi;

class GameMessageService
{
    public function __construct(
        private readonly BotApi $botApi,
        private readonly string $botUsername,
    ) {
    }

    public function sendNewGameMessage(Game $game): void
    {
        $creator = $game->getPlayer(0);
        $result = $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => "\u{1F3B4} *Scopa!*\n\n{$creator->getDisplayName()} wants to play.\nTap the button to join!",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => "\u{1F3B4} Join Game", 'callback_data' => 'join:' . $game->getId()],
                ]],
            ]),
        ]);
    }

    public function sendTableState(Game $game): void
    {
        $text = $this->formatTableState($game);

        if ($game->getTableMessageId() !== null) {
            try {
                $this->botApi->call('editMessageText', [
                    'chat_id' => $game->getChatId(),
                    'message_id' => $game->getTableMessageId(),
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
                return;
            } catch (\Exception) {
                // Message may have been deleted, send a new one
            }
        }

        $result = $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        $game->setTableMessageId($result['message_id'] ?? null);
    }

    public function sendCaptureChoice(Game $game, string $playedCardRef, array $captureOptions): void
    {
        $buttons = [];
        foreach ($captureOptions as $i => $option) {
            $label = implode(' + ', array_map(fn($r) => Card::shortNameFromRef($r), $option));
            $buttons[] = ['text' => $label, 'callback_data' => 'cap:' . $game->getId() . ':' . $i];
        }

        // Arrange buttons in rows of 2
        $keyboard = [];
        foreach (array_chunk($buttons, 2) as $row) {
            $keyboard[] = $row;
        }

        $playedName = Card::shortNameFromRef($playedCardRef);
        $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => "Which cards to capture with {$playedName}?",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }

    public function sendPlayResult(Game $game, Player $player, string $cardRef, array $result): void
    {
        if ($result['isScopa']) {
            $this->botApi->call('sendMessage', [
                'chat_id' => $game->getChatId(),
                'text' => "\u{2B50} *SCOPA!* {$player->getDisplayName()} clears the table!",
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    public function sendRoundScore(Game $game, array $roundScores): void
    {
        $p0 = $game->getPlayer(0);
        $p1 = $game->getPlayer(1);
        $d0 = $roundScores['details'][0];
        $d1 = $roundScores['details'][1];
        $s0 = $roundScores['scores'][0];
        $s1 = $roundScores['scores'][1];

        $lines = ["\u{1F4CA} *Round {$game->getRoundNumber()} Scores*\n"];
        $lines[] = sprintf('`%-16s %4s  %4s`', '', $p0->getDisplayName(), $p1->getDisplayName());
        $lines[] = sprintf(
            '`%-16s %4s  %4s`',
            'Cards',
            ($d0['carte'] ?? '-') . (($d0['carte_won'] ?? false) ? "\u{2705}" : ''),
            ($d1['carte'] ?? '-') . (($d1['carte_won'] ?? false) ? "\u{2705}" : ''),
        );
        $lines[] = sprintf(
            '`%-16s %4s  %4s`',
            'Denari',
            ($d0['denari'] ?? '-') . (($d0['denari_won'] ?? false) ? "\u{2705}" : ''),
            ($d1['denari'] ?? '-') . (($d1['denari_won'] ?? false) ? "\u{2705}" : ''),
        );
        $lines[] = sprintf(
            '`%-16s %4s  %4s`',
            'Sette Bello',
            ($d0['settebello'] ?? false) ? "\u{2705}" : '-',
            ($d1['settebello'] ?? false) ? "\u{2705}" : '-',
        );
        $lines[] = sprintf(
            '`%-16s %4s  %4s`',
            'Primiera',
            ($d0['primiera'] ?? '-') . (($d0['primiera_won'] ?? false) ? "\u{2705}" : ''),
            ($d1['primiera'] ?? '-') . (($d1['primiera_won'] ?? false) ? "\u{2705}" : ''),
        );
        $lines[] = sprintf(
            '`%-16s %4s  %4s`',
            'Scope',
            $d0['scope'] ?? 0,
            $d1['scope'] ?? 0,
        );
        $lines[] = '';
        $lines[] = sprintf("*Round:* +%d / +%d", $s0, $s1);
        $lines[] = sprintf("*Total:* %d / %d", $p0->getScore(), $p1->getScore());

        $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => implode("\n", $lines),
            'parse_mode' => 'Markdown',
        ]);
    }

    public function sendGameOver(Game $game): void
    {
        $p0 = $game->getPlayer(0);
        $p1 = $game->getPlayer(1);
        $winner = $p0->getScore() >= $p1->getScore() ? $p0 : $p1;

        $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => sprintf(
                "\u{1F3C6} *Game Over!*\n\n%s wins!\n\nFinal score: %d - %d\n\nUse /scopa to play again!",
                $winner->getDisplayName(),
                $p0->getScore(),
                $p1->getScore(),
            ),
            'parse_mode' => 'Markdown',
        ]);
    }

    public function sendNotYourTurn(int $chatId, int $messageId): void
    {
        $this->botApi->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => "Not your turn!",
            'reply_parameters' => json_encode(['message_id' => $messageId]),
        ]);
    }

    public function sendDealt(Game $game): void
    {
        $currentPlayer = $game->getCurrentPlayer();
        $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => sprintf(
                "\u{1F0CF} New cards dealt! %d cards left in deck.\n\n\u{1F449} %s's turn \u{2014} type @%s",
                count($game->getDeck()),
                $currentPlayer->getDisplayName(),
                $this->botUsername,
            ),
        ]);
    }

    public function sendNewRound(Game $game): void
    {
        $currentPlayer = $game->getCurrentPlayer();
        $this->botApi->call('sendMessage', [
            'chat_id' => $game->getChatId(),
            'text' => sprintf(
                "\u{1F504} *Round %d*\n\n\u{1F449} %s's turn \u{2014} type @%s",
                $game->getRoundNumber(),
                $currentPlayer->getDisplayName(),
                $this->botUsername,
            ),
            'parse_mode' => 'Markdown',
        ]);
    }

    private function formatTableState(Game $game): string
    {
        $tableCards = $game->getTableCards();
        $tableDisplay = empty($tableCards)
            ? '_empty_'
            : implode('  ', array_map(fn($r) => Card::shortNameFromRef($r), $tableCards));

        $p0 = $game->getPlayer(0);
        $p1 = $game->getPlayer(1);

        $currentPlayer = $game->getCurrentPlayer();

        $scope0 = str_repeat("\u{2B50}", $p0->getScopeCount());
        $scope1 = str_repeat("\u{2B50}", $p1->getScopeCount());

        $lines = [
            "\u{1F3B4} *Scopa* \u{2014} Round {$game->getRoundNumber()}",
            '',
            "Table: {$tableDisplay}",
            '',
            sprintf(
                "\u{1F464} %s: %d cards %s (%d pts)",
                $p0->getDisplayName(),
                count($p0->getCapturedCards()),
                $scope0,
                $p0->getScore(),
            ),
            sprintf(
                "\u{1F464} %s: %d cards %s (%d pts)",
                $p1->getDisplayName(),
                count($p1->getCapturedCards()),
                $scope1,
                $p1->getScore(),
            ),
            '',
            sprintf(
                "\u{1F0CF} %d left \u{2022} \u{1F449} %s's turn",
                count($game->getDeck()),
                $currentPlayer->getDisplayName(),
            ),
        ];

        return implode("\n", $lines);
    }
}
