<?php

namespace App\Controller;

use App\EventSubscriber\TelegramUpdateSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly TelegramUpdateSubscriber $updateSubscriber,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhook/{token}', name: 'telegram_webhook', methods: ['POST'])]
    public function webhook(Request $request, string $token): Response
    {
        $expectedToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

        if ($token !== $expectedToken) {
            $this->logger->warning('Invalid webhook token received');
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $request->getContent();
        $update = json_decode($content, true);

        if ($update === null) {
            $this->logger->error('Invalid JSON in webhook request');
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Received Telegram update', ['update_id' => $update['update_id'] ?? 'unknown']);

        try {
            $this->updateSubscriber->handleUpdate($update);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): Response
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
