<?php

namespace App\EventSubscriber;

use App\Telegram\InlineQueryHandler;
use Psr\Log\LoggerInterface;

class TelegramUpdateSubscriber
{
    public function __construct(
        private readonly InlineQueryHandler $inlineQueryHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['inline_query'])) {
            $this->logger->debug('Processing inline query', [
                'query_id' => $update['inline_query']['id'],
                'query' => $update['inline_query']['query'] ?? '',
            ]);
            $this->inlineQueryHandler->handle($update['inline_query']);
            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        $this->logger->debug('Unhandled update type', ['update' => $update]);
    }

    private function handleMessage(array $message): void
    {
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            return;
        }

        if ($text === '/start') {
            $this->logger->info('User started bot', ['chat_id' => $chatId]);
        }
    }
}
