<?php

namespace App\Telegram;

use BoShurik\TelegramBotBundle\Telegram\Command\AbstractCommand;
use BoShurik\TelegramBotBundle\Telegram\Command\PublicCommandInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class StartCommand extends AbstractCommand implements PublicCommandInterface
{
    public function getName(): string
    {
        return '/start';
    }

    public function getDescription(): string
    {
        return 'Start the bot';
    }

    public function execute(BotApi $api, Update $update): void
    {
        $message = $update->getMessage();

        $api->call('sendMessage', [
            'chat_id' => $message->getChat()->getId(),
            'text' => "Benvenuto a Extra Spicy Scopa! \u{1F0CF}\n\nPlay the classic Italian card game Scopa right here in Telegram.\n\nCommands:\n/newgame - Start a new game\n/join - Join an open game",
            'reply_parameters' => json_encode(['message_id' => $message->getMessageId()]),
        ]);
    }
}
