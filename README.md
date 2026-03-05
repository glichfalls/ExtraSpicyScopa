# Telegram Sticker Bot

A Telegram bot that generates AI-powered stickers using OpenAI's image generation. Create custom stickers on-the-fly using inline mode.

## Usage

In any Telegram chat, type:

```
@your_bot_username 😺 happy orange cat
```

The bot will:
1. Generate an image using OpenAI
2. Convert it to sticker format (512x512 WebP)
3. Add it to your personal sticker pack
4. Return the sticker as an inline result

## Features

- **Inline Mode**: Generate stickers directly in any chat
- **AI-Powered**: Uses OpenAI's gpt-image-1-mini for image generation
- **Auto Sticker Packs**: Automatically creates a personal sticker pack per user
- **Emoji Support**: Include an emoji in your query to associate it with the sticker

## Tech Stack

- **Framework**: Symfony 7
- **Database**: SQLite
- **Image Processing**: Intervention Image
- **Deployment**: Docker + GitHub Actions

## Self-Hosting

### Prerequisites

- Telegram Bot Token (from [@BotFather](https://t.me/BotFather))
- OpenAI API Key
- Server with Docker (Oracle Cloud Free Tier works great)

### Quick Deploy (Oracle Cloud)

1. Create an Ubuntu instance on Oracle Cloud
2. SSH in and run:
   ```bash
   curl -sSL https://raw.githubusercontent.com/glichfalls/ExtraSpicyStickers/master/deploy/setup-from-github.sh | bash -s glichfalls/ExtraSpicyStickers
   ```
3. Edit `/opt/sticker-bot/.env.local` with your tokens
4. Start: `docker compose -f docker-compose.prod.yml up -d`
5. Set webhook: `./deploy/set-webhook.sh YOUR_DOMAIN BOT_TOKEN`

### Environment Variables

| Variable | Description |
|----------|-------------|
| `TELEGRAM_BOT_TOKEN` | Bot token from BotFather |
| `TELEGRAM_BOT_USERNAME` | Bot username (without @) |
| `OPENAI_API_KEY` | OpenAI API key |
| `DATABASE_URL` | SQLite path (default: `var/data.db`) |

### GitHub Actions Auto-Deploy

Add these repository secrets for automatic deployment on push:

- `ORACLE_HOST`: Your server's public IP
- `ORACLE_SSH_KEY`: Private SSH key for server access

## Local Development

```bash
composer install
cp .env .env.local
# Edit .env.local with your tokens
symfony server:start
```

Use ngrok for webhook testing:
```bash
ngrok http 8000
```

## License

MIT
