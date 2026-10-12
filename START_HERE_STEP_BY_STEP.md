# 🚀 TELEGRAM BOT — COMPLETE STEP-BY-STEP SETUP GUIDE

**Bu hujjat:** Proyektni 0 dan ishga tushirish uchun BARCHA qadam-qadam ko'rsatmalar.

**Muddati:** 30-40 daqiqa  
**Qiyinligi:** Oson (faqat terminal commandlar va copy-paste)

---

## ⚠️ SHARTLAR (Oldindan o'rnatilgan bo'lishi kerak)

Terminal'da kiritib, barcha bu commandlar "command not found" qaytarmasa, tayyor:

```bash
# Tekshirig:
docker --version        # Docker installed?
docker-compose --version  # Docker Compose installed?
php --version           # PHP installed?
composer --version      # Composer installed?
```

Agar hammasi o'rnatilgan bo'lsa, davom etin. Agar biror biri yo'q bo'lsa:
- **Mac:** Install Homebrew, keyin `brew install docker composer`
- **Linux:** `sudo apt-get install docker docker-compose php composer`
- **Windows:** Docker Desktop + PHP + Composer

---

## 🎯 OVERALL PLAN

1. **Terminal'da loyiha papkasiga kiring** ✅
2. **Composer dependencies o'rnatish** ✅
3. **`.env` faylini setup qilish** ✅
4. **Database migrations jarayoni** ✅
5. **Telegram bot setup** ✅
6. **Webhook sozlash** ✅
7. **Test qilish** ✅

---

## 📍 STEP 1: TERMINAL'DA PROYEKTGA KIRING

Terminal'ni oching va shu commandni yazib enter bosing:

```bash
cd /Users/macbook/PhpstormProjects/TestProjectV1
```

Tekshirig (siz bu papkada bo'rga bo'lasiz):

```bash
ls -la
```

Agar quyidagilar ko'rinsa, to'g'ri joydasiz:
```
app/
config/
database/
routes/
.env
composer.json
...
```

✅ **Tayyor! Davom etin.**

---

## 🔧 STEP 2: COMPOSER DEPENDENCIES O'RNATISH

Terminal'da (hali shu papkada):

```bash
composer install
```

Bu 2-3 daqiqa suraydi. Tugashgach quyidagi ko'rsatiladi:
```
✓ Composer completed
```

✅ **Done! Davom etin.**

---

## 🌍 STEP 3: `.env` FAYLINI SETUP QILISH

### 3.1 `.env` Faylini Oching

Terminal'da:
```bash
nano .env
```

Yoki PhpStorm'dan `.env` faylini oching (root papkada).

### 3.2 Quyidagi Satrlarni Toping va O'zgartiring

**LINE 52:** `TELEGRAM_BOT_TOKEN`

**OLDIN:**
```
TELEGRAM_BOT_TOKEN=<YOUR_TELEGRAM_BOT_TOKEN_HERE>
```

**KEYIN:** 
Telegram'da `@BotFather` bot'iga kiritib, `/newbot` komandasini yuboring.

Jarayon:
1. Telegram'da @BotFather ni qidirib oching
2. `/newbot` yuboring
3. Bot nomi kiriting (masalan: `MyLibraryBot`)
4. Bot username kiriting (masalan: `my_library_bot_v1`) - oxirida `_bot` bo'lishi kerak
5. BotFather sizga TOKEN beradi, uni copy qilib .env'ga qo'ying

**Misol (real token):**
```
TELEGRAM_BOT_TOKEN=123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh
```

---

**LINE 53:** `TELEGRAM_BOT_USERNAME`

**OLDIN:**
```
TELEGRAM_BOT_USERNAME=<YOUR_BOT_USERNAME>
```

**KEYIN:** 
BotFather'dan olingan username'ni qo'ying (@ belgisisiz).

**Misol:**
```
TELEGRAM_BOT_USERNAME=my_library_bot_v1
```

---

**LINE 54:** `TELEGRAM_WEBHOOK_URL`

**AGAR LOCAL TESTING QILAYOTGAN BO'LSANGIZ (Ngrok ishlatish):**

```bash
# Yangi terminal'da:
brew install ngrok  # Agar o'rnatilmagan bo'lsa

# Keyin:
ngrok http 8000
```

Ngrok sizga URL beradi, masalan:
```
https://1234-567-890-123.ngrok.io
```

Shunga qo'ying:

```
TELEGRAM_WEBHOOK_URL=https://1234-567-890-123.ngrok.io/api/telegram/webhook
```

**AGAR PRODUCTION SERVER VARSA:**

```
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
```

---

**LINE 62:** `ADMIN_USER_ID`

**KEYIN:** Siz ishlasadigan Telegram accountingizning user ID.

Qanday topish:
1. Telegram'da `@userinfobot` ni oching
2. `/start` yuboring
3. U sizga ID beradi
4. Shu ID'ni qo'ying

**Misol:**
```
ADMIN_USER_ID=1234567890
```

---

**LINE 33:** `DB_PASSWORD` (Production uchun)

**LOCAL/TESTING UCHUN:** O'zgartirishning shart emas, qolsa bo'ladi:
```
DB_PASSWORD=password
```

**PRODUCTION UCHUN:** Xavfsiz password:
```
DB_PASSWORD=SuperSecurePassword123!@#$%^&*
```

---

### 3.3 `.env` Ni Saqlang

- **Nano'da:** `Ctrl+X`, keyin `Y`, keyin `Enter`
- **PhpStorm'da:** `Cmd+S`

✅ **.env Tayyor!**

---

## 🗄️ STEP 4: DATABASE VA MIGRATIONS

### 4.1 Sail ni Boshlang

Terminal'da (projectning root papkasida):

```bash
./vendor/bin/sail up -d
```

Bu Docker containers ni boshlaydi (MySQL, Redis, App).

**Kutish:** 30-60 sekund

Tugashgach:
```
✓ Containers started
```

---

### 4.2 Migrations Jarayoni

Terminal'da:

```bash
./vendor/bin/sail artisan migrate
```

Bu barcha table'larni database'da yaratadi.

Tugashgach:
```
✓ Database migrations completed
```

---

### 4.3 Seeders Jarayoni (Categories qo'shish)

Terminal'da:

```bash
./vendor/bin/sail artisan db:seed
```

Bu kategoriyalarni database'ga qo'shadi (Matematika, Fizika, etc.)

Tugashgach:
```
✓ Seeding completed
```

---

### 4.4 Storage Directory O'rnatish

Terminal'da:

```bash
mkdir -p storage/private_materials && chmod 700 storage/private_materials
```

Bu private fayl saqlash uchun folder yaratadi.

✅ **Database Tayyor!**

---

## 🤖 STEP 5: TELEGRAM BOT WEBHOOK SOZLASH

### 5.1 Tinker'ni Oching (Interactive Shell)

Terminal'da:

```bash
./vendor/bin/sail artisan tinker
```

Bundan keyin prompt ko'rsatiladi:
```
Psy Shell v0.x.x
>>>
```

### 5.2 Webhook'ni Set Qilish

Tinker prompt'da quyidagini type qilib enter bosing:

```php
$telegram = app(App\Services\TelegramService::class);
```

Keyin enter bosing, keyin:

```php
$telegram->setWebhook(config('telegram.webhook_url'));
```

**Javob (muvaffaqiyat):**
```
=> [
     "ok" => true,
     "result" => true,
     "description" => "Webhook was set"
   ]
```

✅ **Webhook Set! Davom etin.**

### 5.3 Webhook Info Tekshirish

Tinker'da:

```php
$telegram->getWebhookInfo();
```

**Javob (quyidagicha bo'lishi kerak):**
```
=> [
     "ok" => true,
     "result" => [
       "url" => "https://xxxxx.ngrok.io/api/telegram/webhook",
       "has_custom_certificate" => false,
       "pending_update_count" => 0,
       ...
     ]
   ]
```

Agar `"url"` sizning URL'ingiz bo'lsa, ✅ **MUVAFFAQIYAT!**

### 5.4 Tinker'dan Chiqish

```
exit
```

Yoki `Ctrl+D`

---

## 🧪 STEP 6: TEST QILISH

### 6.1 Bot'ni Telegram'da Qidiring va Boshlang

1. Telegram'da siz yaratgan bot'ni qidirib oching (username bilan)
2. `/start` komandasini yuboring

### 6.2 Bot Javob Berishi Kerak

Bot quyidagi habar yuborishi kerak:

```
Rolni tanlang:

[👨‍🏫 Ustozman]
[🎓 O'quvchiman]
[ℹ️ Yordam]
```

✅ **BOT ISHLAYDI!**

### 6.3 Ro'yxatdan O'tish

1. `🎓 O'quvchiman` tugmasini bosing
2. Ismingizni kiriting
3. Bot tasdiqlama habarini yuborishi kerak

✅ **Barcha ishlaydi!**

---

## 📊 STEP 7: TINKER'DA DATABASE TEKSHIRISH (Optional)

Terminal'da:

```bash
./vendor/bin/sail artisan tinker
```

### 7.1 Barcha Ustozlarni Ko'rish

```php
App\Models\User::all();
```

### 7.2 Kategoriyalarni Ko'rish

```php
App\Models\Category::all();
```

### 7.3 Tablani Ko'rish

```php
DB::table('users')->get();
```

---

## 🎉 TAYYOR!

Agar quyidagilar ishlasa, **LOYIHA TAYYOR:**

✅ Sail containers running  
✅ Database migrations done  
✅ Webhook set  
✅ Bot responds on Telegram  
✅ User registration works  

---

## 🔗 QUYIDAGI BOSQICHLAR (Vibe Coding uchun)

Endi siz:

1. **Teacher Upload** feature qo'shish mumkin
2. **Student Search** logic implement qilish mumkin
3. **Admin functions** razvay qilish mumkin
4. **Tests yozish** mumkin

Har bir feature uchun quyidagi fayllarni ishlatib:

- **app/Http/Controllers/** - HTTP logic
- **app/Services/** - Business logic
- **app/Models/** - Database queries
- **routes/api.php** - Routes qo'shish

---

## ❌ XATOLAR VA FIXLAR

### "Connection refused" xatosi

**Sababu:** Docker containers running emas.

**Fix:**
```bash
./vendor/bin/sail up -d
```

---

### "TELEGRAM_BOT_TOKEN not set" xatosi

**Sababu:** `.env`'da token bo'sh.

**Fix:**
1. `.env` faylini oching
2. LINE 52'da token qo'ying
3. Saqlang
4. Terminal'da: `./vendor/bin/sail artisan cache:clear`

---

### "Database connection failed"

**Sababu:** Migrations run qilinmadi.

**Fix:**
```bash
./vendor/bin/sail artisan migrate --fresh --seed
```

---

### "Webhook not responding"

**Sababu:** HTTPS yo'q yoki URL noto'g'ri.

**Fix:**
1. Webhook URL HTTPS bo'lishi kerak (http emas!)
2. Ngrok'dan yangi URL oling: `ngrok http 8000`
3. `.env`'da yangi URL qo'ying
4. Tinker'da qayta set qiling:
```php
$telegram->setWebhook(config('telegram.webhook_url'));
```

---

### "mkdir: permission denied"

**Sababu:** Folder permissions.

**Fix:**
```bash
sudo mkdir -p storage/private_materials
sudo chmod 700 storage/private_materials
sudo chown $(whoami) storage/private_materials
```

---

## 📞 QUYIDA MUHIM COMMANDLAR

```bash
# Sail boshlash
./vendor/bin/sail up -d

# Sail to'xtatish
./vendor/bin/sail down

# Database logs ko'rish
./vendor/bin/sail logs mysql

# App logs ko'rish
./vendor/bin/sail logs laravel.test

# Fresh reset (barcha data o'chib ketadi!)
./vendor/bin/sail artisan migrate:fresh --seed

# Tinker
./vendor/bin/sail artisan tinker

# SSH container'ga kirish
./vendor/bin/sail shell

# Queue worker (background jobs)
./vendor/bin/sail artisan queue:work

# Cache clear
./vendor/bin/sail artisan cache:clear

# Config clear
./vendor/bin/sail artisan config:clear
```

---

## 📖 DOKUMENTATSIYA FAYLLAR

Agar biror narsani tushunmovchi bo'lsangiz:

1. **README_QUICK_INDEX.md** - Overview
2. **COMPLETE_SUMMARY.md** - All tokens
3. **SETUP_CHECKLIST.md** - Deployment
4. **IMPLEMENTATION_GUIDE.md** - Architecture

---

## ✅ FINAL CHECKLIST

```
□ .env'da 4 token filled (TOKEN, USERNAME, WEBHOOK_URL, ADMIN_ID)
□ composer install ✅
□ ./vendor/bin/sail up -d ✅
□ ./vendor/bin/sail artisan migrate ✅
□ ./vendor/bin/sail artisan db:seed ✅
□ mkdir storage/private_materials ✅
□ Webhook set (tinker'da) ✅
□ Bot /start qabul qiladi ✅
□ User registration ishlaydi ✅
```

**Hamma ✅ bo'lsa, LOYIHA TAYYOR!** 🚀

---

## 🎓 KEYINGI STEP

Endi siz quyidagilarni qila olasiz:

1. **Yangisini Feature qo'shish:** Models → Migrations → Services → Controllers → Routes
2. **Test yozish:** `tests/Feature/` fayllarida
3. **Database toyishni tekshirish:** `./vendor/bin/sail artisan tinker` + queries
4. **Production uchun:** SETUP_CHECKLIST.md qo'llanmasini o'qing

---

## 💡 PRO TIPS

1. **Har doim Sail running bo'lsin:** `./vendor/bin/sail up -d` (background'da)

2. **Logs ko'ring:** `./vendor/bin/sail tail` (har nima bo'layotganini ko'rsatadi)

3. **Fresh start:** `./vendor/bin/sail artisan migrate:fresh --seed` (butun database o'chilib qayta yaratiladi)

4. **Tinker juda foydali:** Database'ni test qilish uchun ideal

---

## 🎉 TUGADI!

**Shunga ehtiyoj! Loyiha ishga tushdi!**

Endi vibe coding'ni boshlash mumkin. 💻🚀

**Savollar? Bu faylni AI'ga pass qiling - barcha qadam-qadam yozilgan!**
