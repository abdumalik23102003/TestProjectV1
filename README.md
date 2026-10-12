# Telegram Kutubxona Boti

Ustozlar va o'quvchilar uchun ta'lim materiallarini Telegram orqali xavfsiz saqlash, tartiblash va qidiruv qilish botı.

## Xususiyatlari

- ✅ Ustoz/o'quvchi/admin rollari
- ✅ Fayl yuklash (document, audio, video, photo)
- ✅ Public/Private ko'rinish
- ✅ FULLTEXT qidiruv
- ✅ Versiyalash va soft delete
- ✅ Audit logging
- ✅ Admin panel asoslari
- ✅ Signed URL katta fayllar uchun

## Lokal Ishga Tushirish

### Shartlar
- Docker + Docker Compose
- Composer
- PHP 8.3+

### Qadam-qadam

```bash
# Proyektga kiring
cd /Users/macbook/PhpstormProjects/TestProjectV1

# Composer o'rnatish
composer install

# .env sozlash (TELEGRAM_BOT_TOKEN va boshqalar)
cp .env.example .env
nano .env

# Sail boshlang
./vendor/bin/sail up -d

# Database sozlash
./vendor/bin/sail artisan migrate:fresh --seed

# Bot testing (polling, development only)
./vendor/bin/sail artisan telegram:poll
```

## Environment Variables

```dotenv
# Telegram
TELEGRAM_BOT_TOKEN=<your_token_here>
TELEGRAM_BOT_USERNAME=<your_bot_username>
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=<random_string>
ADMIN_TELEGRAM_IDS=<comma_separated_ids>

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=telegram_library_bot
DB_USERNAME=sail
DB_PASSWORD=password

# File Storage
APP_MAX_FILE_SIZE_MB=20
TEACHER_STORAGE_QUOTA_GB=5
MAX_UPLOADS_PER_DAY=100
```

## Testlar

```bash
# Hammasi
./vendor/bin/sail artisan test

# Bitta file
./vendor/bin/sail artisan test tests/Feature/TeacherUploadTest.php
```

## Webhook Setup

```bash
./vendor/bin/sail artisan telegram:set-webhook
./vendor/bin/sail artisan telegram:webhook-info
```

## Deploy

Production deploy uchun `docs/DEPLOY.md` ga qarang.

## Tech Stack

- Laravel 13
- PHP 8.3
- MySQL 8.4
- Telegram Bot API
- Docker + Sail

## Litsenziya

MIT

**Maintainer:** Abdumalik  
**Last Updated:** Oct 12, 2026
