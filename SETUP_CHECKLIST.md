# ⚙️ SETUP CHECKLIST — Qoldirgan Keylar va O'zgartirishlar

Bu hujjat barcha `<...>` placeholder joylarini, tokenlarni, parol va muhim sozlamalarni ro'yxatga oladi. **Loyiha Deploy qilishdan oldin hamma narsani o'zgartirish kerak!**

---

## 📌 PRIORITY 1: MUHIM KEYLAR (Setup qilish shart)

### 1. Telegram Bot Token
**Joyi:** `.env`
```
TELEGRAM_BOT_TOKEN=<YOUR_TELEGRAM_BOT_TOKEN_HERE>
```

**Nima qilish kerak:**
1. Telegram'da `@BotFather` botiga yozing
2. `/newbot` komandasini yuboring
3. Bot nomi va username kiriting
4. Berilgan tokeni `.env`'dagi `TELEGRAM_BOT_TOKEN` orniga qo'ying

**Misol:**
```
TELEGRAM_BOT_TOKEN=123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh
```

---

### 2. Telegram Bot Username
**Joyi:** `.env`, `config/telegram.php`
```
TELEGRAM_BOT_USERNAME=<YOUR_BOT_USERNAME>
```

**Nima qilish kerak:**
- BotFather'dan bo'lgan bot username'ini kiriting (@ belgisisiz)

**Misol:**
```
TELEGRAM_BOT_USERNAME=my_library_bot
```

---

### 3. Webhook URL (Production)
**Joyi:** `.env`, `config/telegram.php`
```
TELEGRAM_WEBHOOK_URL=<YOUR_DOMAIN_COM>/api/telegram/webhook
```

**Nima qilish kerak:**
1. Sizning server domain'i kiriting (HTTPS majburiy!)
2. Local test uchun ngrok ishlatish mumkin:
   ```bash
   ngrok http 8000
   ```
   Keyin webhook URL:
   ```
   TELEGRAM_WEBHOOK_URL=https://xxxxx-xx-xxx-xxx.ngrok.io/api/telegram/webhook
   ```

**Misol:**
```
TELEGRAM_WEBHOOK_URL=https://mybot.example.com/api/telegram/webhook
```

---

### 4. Admin User ID
**Joyi:** `.env`, `config/materials.php`
```
ADMIN_USER_ID=<YOUR_TELEGRAM_ADMIN_ID>
```

**Nima qilish kerak:**
1. Telegram'da bot'ga `/start` yuboring
2. Bot'dan maxbaralar log'ini ko'ring (server logs)
3. Telegram user ID'ni toping (siz ishlasadigan akkaunt)
4. `.env`'ga kiriting

**Misol:**
```
ADMIN_USER_ID=1234567890
```

---

### 5. Database Konfiguratsiyasi
**Joyi:** `.env`
```
DB_DATABASE=telegram_library_bot
DB_USERNAME=sail
DB_PASSWORD=password
```

**Nima qilish kerak:**
- Local development uchun (Sail ichida) default qolsa bo'ladi
- Production uchun xavfsiz parol kiriting

**Production parol:**
```
DB_PASSWORD=<STRONG_RANDOM_PASSWORD_32_CHARS>
```

---

## 📌 PRIORITY 2: QUEUE VA ASYNC JOBS

**Joyi:** `.env`
```
QUEUE_CONNECTION=sync
```

**Nima qilish kerak:**
- Local: `sync` qolsa bo'ladi (real-time)
- Production uchun:
  ```
  QUEUE_CONNECTION=database
  ```
  Va queue worker boshlash:
  ```bash
  ./vendor/bin/sail artisan queue:work
  ```

---

## 📌 PRIORITY 3: SESSION VA COOKIES

**Joyi:** `.env`
```
SESSION_DOMAIN=null
SESSION_LIFETIME=120
```

**Production uchun:**
```
SESSION_DOMAIN=.example.com
SESSION_LIFETIME=120
```

---

## 📌 PRIORITY 4: LOGGING VA DEBUG

**Joyi:** `.env`
```
APP_DEBUG=true
LOG_LEVEL=debug
```

**Production uchun:**
```
APP_DEBUG=false
LOG_LEVEL=warning
```

---

## 📌 PRIORITY 5: EMAIL (OPTIONAL, Hozircha shart emas)

**Joyi:** `.env`
```
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@example.com"
```

**Taqvasi:** Bot foydalanuvchilarga e-mail bermaydi (faqat Telegram), shuning uchun hozir o'zgartirishning shart emas.

---

## 📌 PRIORITY 6: FILE STORAGE LIMITS

**Joyi:** `.env`, `config/materials.php`

```
APP_MAX_FILE_SIZE_MB=20
TEACHER_STORAGE_QUOTA_GB=5
MAX_UPLOADS_PER_DAY=100
SIGNED_URL_EXPIRY_MINUTES=10
```

**Nima bu?**
- **APP_MAX_FILE_SIZE_MB:** Bir faylning maksimal hajmi (20 MB)
- **TEACHER_STORAGE_QUOTA_GB:** Har bir ustoz uchun umumiy disk joyi (5 GB)
- **MAX_UPLOADS_PER_DAY:** Kunlik maksimal upload (100 ta)
- **SIGNED_URL_EXPIRY_MINUTES:** Katta fayllar uchun imzolangan linkni amalga kelish vaqti (10 daqiqa)

**Production uchun o'zgartirishlar:**
```
APP_MAX_FILE_SIZE_MB=100           # Xohlagan hajmga
TEACHER_STORAGE_QUOTA_GB=50        # Xohlagan hajmga
MAX_UPLOADS_PER_DAY=500            # Xohlagan soniga
SIGNED_URL_EXPIRY_MINUTES=5        # Xavfsizlik uchun qisqaroq
```

---

## 📌 PRIORITY 7: WIZARD SESSION TIMEOUT

**Joyi:** `.env`, `config/materials.php`

```
WIZARD_SESSION_TIMEOUT=15
```

**Nima bu:** Fayl yuklash wizardida sessiyaning amal qilish vaqti (15 daqiqa).

**O'zgartirishlar:** Kerakli bo'lsa, minut sonini o'zgartiring.

---

## 🚀 DEPLOYMENT STEPS

### Step 1: Composer Dependencies
```bash
# Terminal'da loyiha rootida:
composer install

# Yoki Sail ichida:
./vendor/bin/sail composer install
```

### Step 2: Database Migration
```bash
# Migrations jarayoni
./vendor/bin/sail artisan migrate

# Seed (categories va boshqalar)
./vendor/bin/sail artisan db:seed
```

### Step 3: Webhook Setup
```bash
# Bot'ga webhook URL'ni sozlash (Production)
./vendor/bin/sail artisan tinker

# Tinker ichida:
$telegram = app(App\Services\TelegramService::class);
$telegram->setWebhook(config('telegram.webhook_url'));

# Response ko'rishingiz kerak: {'ok': true, ...}
```

### Step 4: Queue Worker (Production)
```bash
# Database queue uchun migrations
./vendor/bin/sail artisan migrate

# Queue worker boshlash (background process)
./vendor/bin/sail artisan queue:work --daemon
```

### Step 5: Scheduler (Production)
```bash
# Cron job sozlash (har minutda tekshiriladi)
crontab -e

# Quyidagini qo'shing:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔐 SECURITY CHECKLIST

- ✅ `.env` faylini `.gitignore`'ga qo'shing (Git'da yuklama!)
- ✅ APP_KEY random qilish: `./vendor/bin/sail artisan key:generate`
- ✅ HTTPS webhook (production)
- ✅ Database password xavfsiz qilish
- ✅ `config/telegram.php` token'ni `.env`'dan o'qish
- ✅ Admin ID faqat sizniki bo'lsin
- ✅ Private materials folderiga direct web access yo'q

---

## ⚡ LOCAL DEVELOPMENT (Testing)

### Sail boshlash:
```bash
./vendor/bin/sail up -d

# Yoki first time uchun:
./vendor/bin/sail up
```

### Migrations jarayoni:
```bash
./vendor/bin/sail artisan migrate --fresh --seed
```

### Telegram updatelarni test qilish (ngrok):
```bash
# Terminal 1: Sail
./vendor/bin/sail up

# Terminal 2: ngrok
ngrok http 8000

# Terminal 3: Webhook setup
./vendor/bin/sail artisan tinker
$telegram = app(App\Services\TelegramService::class);
$telegram->setWebhook('https://xxxxx.ngrok.io/api/telegram/webhook');
```

### Queue test (sync rejimi):
```bash
# .env'da QUEUE_CONNECTION=sync bo'lsa, jobs hali bajariladi (test uchun ideal)
```

---

## 📋 FINAL CHECKLIST (Barcha narsani tekshirlang!)

- ✅ `TELEGRAM_BOT_TOKEN` — `.env`'da kiriting
- ✅ `TELEGRAM_BOT_USERNAME` — `.env`'da kiriting
- ✅ `TELEGRAM_WEBHOOK_URL` — production uchun sizning domain'ni kiriting
- ✅ `ADMIN_USER_ID` — siz ishlasadigan Telegram ID
- ✅ `DB_DATABASE=telegram_library_bot` — o'zgartirildi
- ✅ `DB_USERNAME=sail`, `DB_PASSWORD=password` — Sail uchun to'g'ri
- ✅ `.env` file `.gitignore`'da
- ✅ Migrations jarayoni (`./vendor/bin/sail artisan migrate`)
- ✅ Categories seed (`./vendor/bin/sail artisan db:seed`)
- ✅ `storage/private_materials` direktoryasi yaratilgan
- ✅ config/telegram.php to'g'ri qilingan
- ✅ config/materials.php to'g'ri qilingan
- ✅ routes/api.php webhook route'i mavjud
- ✅ Barcha Models yaratilgan (User, Material, etc.)
- ✅ Barcha Services yaratilgan (TelegramService, SessionService, etc.)
- ✅ Barcha Controllers yaratilgan (AuthController, TeacherController, etc.)
- ✅ ProcessTelegramUpdate Job mavjud
- ✅ Helper function (formatBytes) ichida

---

## 🎯 RUNNING THE BOT

### Production (Server):
```bash
# 1. Migrations
./vendor/bin/sail artisan migrate

# 2. Queue worker (background)
./vendor/bin/sail artisan queue:work --daemon

# 3. Scheduler
# Crontab qo'shilgan

# 4. Bot o'zgartirlari
# Webhook berilgan

# ✅ Bot ishlaydi!
```

### Local Testing:
```bash
# Terminal 1:
./vendor/bin/sail up -d

# Terminal 2:
./vendor/bin/sail artisan migrate --fresh --seed

# Terminal 3:
ngrok http 8000
# Webhook setup (tinker'da)

# ✅ Bot qabul qilishga tayyor!
```

---

## 🆘 COMMON ERRORS

### "TELEGRAM_BOT_TOKEN not set"
**Fix:** `.env`'da `TELEGRAM_BOT_TOKEN` kiriting

### "Database connection refused"
**Fix:** `./vendor/bin/sail up -d` dan o'ng, migrations jarayoni: `./vendor/bin/sail artisan migrate`

### "File not found in private storage"
**Fix:** `storage/private_materials` direktoryasi mavjudmi? `./vendor/bin/sail artisan migrate` qayta bajaring

### "Webhook not working"
**Fix:** HTTPS va to'g'ri URL'ni tekshiring. Ngrok uchun oxirgi URL kiriting.

---

## 📞 SUPPORT FILES

Barcha file'lar shu yerda:
- `/app/Http/Controllers/` — Controllers
- `/app/Services/` — Business logic
- `/app/Models/` — Database models
- `/database/migrations/` — DB schema
- `/config/` — telegram.php, materials.php
- `/routes/api.php` — Webhook routes

**Barcha narsani setup qilgandan keyin, bot ishlashda tayyor bo'ladi!** 🚀
