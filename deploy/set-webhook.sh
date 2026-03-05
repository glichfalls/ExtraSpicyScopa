#!/bin/bash

# Set Telegram Webhook Script

if [ -z "$1" ]; then
    # Try to read from .env
    if [ -f .env ]; then
        source .env
        DOMAIN=$WEBHOOK_DOMAIN
        TOKEN=$TELEGRAM_BOT_TOKEN
    fi
else
    DOMAIN=$1
    TOKEN=$2
fi

if [ -z "$DOMAIN" ] || [ -z "$TOKEN" ]; then
    echo "Usage: $0 <domain> <bot_token>"
    echo "   or: Set WEBHOOK_DOMAIN and TELEGRAM_BOT_TOKEN in .env"
    exit 1
fi

WEBHOOK_URL="https://${DOMAIN}/webhook/${TOKEN}"

echo "Setting webhook to: https://${DOMAIN}/webhook/***"

RESPONSE=$(curl -s -X POST "https://api.telegram.org/bot${TOKEN}/setWebhook" \
    -H "Content-Type: application/json" \
    -d "{\"url\": \"${WEBHOOK_URL}\"}")

echo "Response: $RESPONSE"

# Verify webhook
echo ""
echo "Verifying webhook..."
curl -s "https://api.telegram.org/bot${TOKEN}/getWebhookInfo" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
