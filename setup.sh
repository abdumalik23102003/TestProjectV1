#!/bin/bash

# 🚀 TELEGRAM LIBRARY BOT — Quick Start Script
# Bu script loyiyaniyni to'g'ri sozlashga yordam beradi

set -e

echo "========================================="
echo "🚀 Telegram Library Bot — Setup"
echo "========================================="

# Step 1: Composer
echo ""
echo "1️⃣  Installing Composer dependencies..."
composer install

# Step 2: .env setup
echo ""
echo "2️⃣  Setting up .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ .env created from .env.example"
else
    echo "✅ .env already exists"
fi

# Step 3: APP_KEY
echo ""
echo "3️⃣  Generating APP_KEY..."
./vendor/bin/sail artisan key:generate

# Step 4: Database migrations
echo ""
echo "4️⃣  Running migrations..."
./vendor/bin/sail artisan migrate

# Step 5: Database seeders
echo ""
echo "5️⃣  Seeding database (categories)..."
./vendor/bin/sail artisan db:seed

# Step 6: Storage directories
echo ""
echo "6️⃣  Creating storage directories..."
mkdir -p storage/private_materials
chmod 700 storage/private_materials
echo "✅ storage/private_materials created (permissions: 700)"

# Step 7: Info
echo ""
echo "========================================="
echo "✅ SETUP COMPLETE!"
echo "========================================="
echo ""
echo "📝 NEXT STEPS:"
echo ""
echo "1. Configure .env with Telegram settings:"
echo "   - TELEGRAM_BOT_TOKEN=<YOUR_BOT_TOKEN>"
echo "   - TELEGRAM_BOT_USERNAME=<YOUR_BOT_USERNAME>"
echo "   - TELEGRAM_WEBHOOK_URL=<YOUR_WEBHOOK_URL>"
echo "   - ADMIN_USER_ID=<YOUR_ADMIN_ID>"
echo ""
echo "   See: SETUP_CHECKLIST.md for detailed instructions"
echo ""
echo "2. Start Sail:"
echo "   ./vendor/bin/sail up -d"
echo ""
echo "3. Set webhook (Tinker):"
echo "   ./vendor/bin/sail artisan tinker"
echo "   \$telegram = app(App\\Services\\TelegramService::class);"
echo "   \$telegram->setWebhook(config('telegram.webhook_url'));"
echo ""
echo "4. Start queue worker (production):"
echo "   ./vendor/bin/sail artisan queue:work"
echo ""
echo "📖 Documentation:"
echo "   - SETUP_CHECKLIST.md"
echo "   - IMPLEMENTATION_GUIDE.md"
echo "   - TELEGRAM_BOT_TZ_LARAVEL_SAIL.md"
echo ""
echo "🤖 Ready to go! 🚀"
