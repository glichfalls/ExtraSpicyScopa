<?php

namespace App\Telegram;

use App\Service\GameMessageService;
use App\Service\ScopaGameService;
use BoShurik\TelegramBotBundle\Telegram\Command\AbstractCommand;
use BoShurik\TelegramBotBundle\Telegram\Command\PublicCommandInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class ScopaCommand extends AbstractCommand implements PublicCommandInterface
{
    public function __construct(
        private readonly ScopaGameService $gameService,
        private readonly GameMessageService $messageService,
    ) {
    }

    public function getName(): string
    {
        return '/scopa';
    }

    public function getDescription(): string
    {
        return 'Start a new Scopa game';
    }

    public function execute(BotApi $api, Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $from = $message->getFrom();

        // Only allow in group chats
        $chatType = $message->getChat()->getType();
        if ($chatType === 'private') {
            $api->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => "Add me to a group chat to play Scopa!",
                'reply_parameters' => json_encode(['message_id' => $message->getMessageId()]),
            ]);
            return;
        }

        // Check for existing active game
        $existing = $this->gameService->findActiveGameForChat($chatId);
        if ($existing !== null) {
            $api->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => "A game is already active in this chat!",
                'reply_parameters' => json_encode(['message_id' => $message->getMessageId()]),
            ]);
            return;
        }

        $user = $this->gameService->findOrCreateUser(
            $from->getId(),
            $from->getFirstName(),
            $from->getUsername(),
        );

        $game = $this->gameService->createGame($chatId, $user);
        $this->messageService->sendNewGameMessage($game);
    }
}
