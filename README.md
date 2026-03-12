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
- **Database**: MySQL
- **Image Processing**: Intervention Image
- **Hosting**: Plesk (srv1.netlabs.dev)
- **Deployment**: GitHub Actions

## Self-Hosting

### Prerequisites

- Telegram Bot Token (from [@BotFather](https://t.me/BotFather))
- OpenAI API Key
- Server with PHP 8.4, MySQL, and Composer

### Environment Variables

| Variable | Description |
|----------|-------------|
| `TELEGRAM_BOT_TOKEN` | Bot token from BotFather |
| `TELEGRAM_BOT_USERNAME` | Bot username (without @) |
| `OPENAI_API_KEY` | OpenAI API key |
| `DATABASE_URL` | MySQL connection string |
| `APP_SECRET` | Random 64-char hex string (`openssl rand -hex 32`) |

### GitHub Actions Auto-Deploy

Pushes to `master` automatically deploy to the server. Add these repository secrets:

| Secret | Description |
|--------|-------------|
| `SERVER_HOST` | Server IP address |
| `SERVER_USER` | SSH username |
| `SERVER_SSH_KEY` | Private SSH key for server access |
| `APP_SECRET` | Symfony app secret |
| `TELEGRAM_BOT_TOKEN` | Bot token |
| `TELEGRAM_BOT_USERNAME` | Bot username |
| `OPENAI_API_KEY` | OpenAI API key |
| `DATABASE_URL` | MySQL connection string |

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