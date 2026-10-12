# Telegram Kutubxona Boti — Claude Coding Assistant Qoidalari

Bu fayl Claude AI va boshqa model asistentlar uchun loyihani kodlash qoidalarini belgilaydi.

## Asosiy Qoidalar

1. **"Xatoni ushlab, avtomatik davom etish" yo'li:** Boshqaruv faqat `migrate:fresh --seed` va `test` chaqiroviga beriladi. Agar muvaffaqiyatli bo'lsa, quyidagi bosqichga o'tish. Agar xato bo'lsa, avtomatik tuzatma va qayta urinish.

2. **Vibe Coding Uslubi:** Kodni dastlab hammasini tugatib yozing, so'ng tekshiring. Oraliq tekshiruv yo'q.

3. **Telegram API Ochishini Hamqoşla:** 
   - Token loglarda yo'k bo'lsin (`***` bilan almashtir)
   - Hamma Telegram chaqiruvi `TelegramClient` orqali
   - Mock'lash uchun `Http::fake()` 

4. **Database:** MySQL/MariaDB asosiy (FULLTEXT qidiruv), SQLite zaxira (LIKE).

5. **Xavfsizlik:**
   - Webhook secret `X-Telegram-Bot-Api-Secret-Token` bilan
   - Role va holat tekshiruvi har callbackda
   - File path UUID asosida
   - HTML-escape user input

6. **Tests:** Feature va Unit testlar, `RefreshDatabase`, `Http::fake()`.

7. **Lang:** Barcha foydalanuvchi matni `lang/uz/bot.php` ichida, hardkod yo'q.

## Development Workflow

```bash
# Yangi feature
php artisan make:migration ...
php artisan make:model ...
# Kodla
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test

# Commit (test o'tgan keyin)
git add .
git commit -m "Feature: ..."
```

## Bosqichlar (E0-E8)

Har bosqich TZ v2 1-8 bo'limlarida belgilangan. E0 dan E8 gacha vibe coding orqali.

---

**Prepared for:** AI Assistants (Claude, GPT, LLaMA)  
**Version:** 2.0  
**Date:** Oct 12, 2026
