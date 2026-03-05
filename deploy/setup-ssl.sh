#!/bin/bash
set -e

# SSL Setup Script using Let's Encrypt

if [ -z "$1" ]; then
    echo "Usage: $0 <domain>"
    echo "Example: $0 sticker-bot.example.com"
    exit 1
fi

DOMAIN=$1
EMAIL=${2:-"admin@$DOMAIN"}

echo "=== Setting up SSL for $DOMAIN ==="

# Create SSL directory
mkdir -p docker/ssl

# Stop nginx temporarily if running
docker compose stop nginx 2>/dev/null || true

# Get certificate
docker run --rm \
    -v "$(pwd)/docker/ssl:/etc/letsencrypt" \
    -v "certbot-webroot:/var/www/certbot" \
    -p 80:80 \
    certbot/certbot certonly \
    --standalone \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    -d "$DOMAIN"

# Update nginx config
echo "Updating nginx configuration..."
sed -i "s/YOUR_DOMAIN/$DOMAIN/g" docker/default.conf

# Uncomment HTTPS server block
sed -i 's/# server {/server {/g' docker/default.conf
sed -i 's/#     listen 443/    listen 443/g' docker/default.conf
sed -i 's/#     /    /g' docker/default.conf

# Uncomment HTTP redirect
sed -i 's/# location \/ {/location \/ {/g' docker/default.conf
sed -i 's/#     return 301/    return 301/g' docker/default.conf

echo ""
echo "=== SSL setup complete! ==="
echo ""
echo "Restart the services with: docker compose up -d"
