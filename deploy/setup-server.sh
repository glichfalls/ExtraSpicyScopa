#!/bin/bash
set -e

# Oracle Cloud Free Tier Server Setup Script
# Run this script on a fresh Ubuntu 22.04 instance

echo "=== Sticker Bot Server Setup ==="

# Update system
echo "Updating system packages..."
sudo apt-get update
sudo apt-get upgrade -y

# Install Docker
echo "Installing Docker..."
sudo apt-get install -y apt-transport-https ca-certificates curl software-properties-common
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Add current user to docker group
sudo usermod -aG docker $USER

# Install git
echo "Installing Git..."
sudo apt-get install -y git

# Configure firewall
echo "Configuring firewall..."
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save

# Create application directory
echo "Creating application directory..."
sudo mkdir -p /opt/sticker-bot
sudo chown $USER:$USER /opt/sticker-bot

echo ""
echo "=== Server setup complete! ==="
echo ""
echo "Next steps:"
echo "1. Log out and log back in (for docker group)"
echo "2. Clone your repository to /opt/sticker-bot"
echo "3. Copy .env.example to .env and configure"
echo "4. Run: cd /opt/sticker-bot && docker compose up -d"
echo "5. Set up SSL with: ./deploy/setup-ssl.sh YOUR_DOMAIN"
echo "6. Set webhook with: ./deploy/set-webhook.sh"
