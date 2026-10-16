#!/usr/bin/env bash
# Loyihani Docker Sail orqali birinchi marta ishga tushirish.
set -euo pipefail
cd "$(dirname "$0")"

FRESH_ENV=0
if [ ! -f .env ]; then
    cp .env.example .env
    FRESH_ENV=1
    echo "✅ .env yaratildi"
fi

if [ ! -x vendor/bin/sail ]; then
    echo "📦 Composer paketlari o'rnatilmoqda (Docker orqali)..."
    docker run --rm -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" -w /var/www/html \
        laravelsail/php84-composer:latest composer install --ignore-platform-reqs
fi

./vendor/bin/sail up -d

if grep -q '^APP_KEY=$' .env; then
    ./vendor/bin/sail artisan key:generate
fi

echo "⏳ MySQL tayyor bo'lishi kutilmoqda va migratsiyalar bajarilmoqda..."
for attempt in $(seq 1 30); do
    if ./vendor/bin/sail artisan migrate --force >/dev/null 2>&1; then
        break
    fi
    if [ "$attempt" -eq 30 ]; then
        echo "❌ Migratsiya bajarilmadi. 'sail logs mysql' ni tekshiring."
        exit 1
    fi
    sleep 2
done

if [ "$FRESH_ENV" -eq 1 ]; then
    ./vendor/bin/sail artisan db:seed --force
fi

echo ""
echo "✅ Tayyor. Keyingi qadamlar:"
echo "  1) .env ichida TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME va ADMIN_TELEGRAM_IDS ni to'ldiring"
echo "  2) Lokal sinov:  ./vendor/bin/sail artisan telegram:poll"
echo "  3) Testlar:      ./vendor/bin/sail artisan test"
