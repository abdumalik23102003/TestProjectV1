# Production'ga joylash

## Talablar

- PHP 8.3+ (`mbstring`, `pdo_mysql`, `fileinfo`, `zip`, `intl`)
- MySQL 8.x
- Veb-server (Nginx/Apache), root: `public/`
- **HTTPS** va ochiq domen (Telegram webhook uchun)

## Birinchi o'rnatish

```bash
git clone <repo> && cd <repo>
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

`.env` da albatta o'zgartiring:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domen.uz
TELEGRAM_BOT_TOKEN=...
TELEGRAM_BOT_USERNAME=...
TELEGRAM_WEBHOOK_URL=https://domen.uz/api/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=<uzun tasodifiy satr>   # majburiy
ADMIN_TELEGRAM_IDS=<admin telegram id lari>
```

```bash
php artisan migrate --force
php artisan db:seed --force          # faqat birinchi marta (kategoriyalar)
php artisan config:cache
php artisan telegram:set-webhook
php artisan telegram:webhook-info    # tekshirish
```

Eslatma: `route:cache` ishlatmang (`routes/web.php` da closure bor).

## Cron (majburiy)

Tozalash va sessiya muddatlari Laravel scheduler orqali ishlaydi:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Fayllar va zaxira

- Fayllar `storage/private_materials/` da saqlanadi. Papka veb orqali ochiq bo'lmasligi va `www-data` yoza olishi kerak.
- Zaxiraga oling: MySQL bazasi **va** `storage/private_materials/`.
- Loglar: `storage/logs/`. Token loglarda maskalanadi.

## Yangilash

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
```

## Tekshiruv ro'yxati

- [ ] `APP_DEBUG=false`
- [ ] `TELEGRAM_WEBHOOK_SECRET` o'rnatilgan (bo'sh bo'lsa webhook 403 qaytaradi)
- [ ] `telegram:webhook-info` da `last_error_message` yo'q
- [ ] Cron ishlayapti
- [ ] Baza va `storage/private_materials/` zaxirasi sozlangan
