#!/bin/bash

set -e

echo "🚀 Starting devcontainer setup..."

# Wait for DDEV to be available
echo "⏳ Waiting for DDEV to be ready..."
max_attempts=30
attempt=0

while ! command -v ddev &> /dev/null; do
    if [ $attempt -ge $max_attempts ]; then
        echo "❌ DDEV not found after ${max_attempts} attempts"
        exit 1
    fi
    echo "   Waiting for DDEV... (attempt $((attempt + 1))/${max_attempts})"
    sleep 2
    attempt=$((attempt + 1))
done

echo "✅ DDEV found!"

echo "🔧 Starting DDEV..."
ddev start -y

echo "📝 Creating .env file..."
cp .env.example .env
ddev artisan key:generate --ansi

echo "📝 Creating .env.testing file..."
cp .env.example .env.testing
ddev artisan key:generate --env=testing --ansi

echo "🗄️  Setting up database..."
ddev task db:fresh

echo "🎉 Devcontainer setup complete!"
