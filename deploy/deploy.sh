#!/bin/bash
set -e

# Deployment Script
# Run this to deploy updates

echo "=== Deploying Sticker Bot ==="

cd /opt/sticker-bot

# Pull latest changes
echo "Pulling latest changes..."
git pull origin main

# Build and restart containers
echo "Building and restarting containers..."
docker compose down
docker compose build --no-cache
docker compose up -d

# Run database migrations
echo "Running database migrations..."
docker compose exec app php bin/console doctrine:schema:update --force --no-interaction

# Clear cache
echo "Clearing cache..."
docker compose exec app php bin/console cache:clear --env=prod

# Show status
echo ""
echo "=== Deployment complete! ==="
docker compose ps
