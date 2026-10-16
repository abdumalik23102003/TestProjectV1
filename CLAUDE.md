# Telegram Kutubxona Boti: kodlash qoidalari

Stack: Laravel 13, PHP 8.3+, MySQL 8.4, Docker Sail, Telegram Bot API.

## Ish tartibi

Mahalliy kompyuterda PHP bo'lmasligi mumkin: barcha buyruqlar Sail orqali ishlaydi.

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail composer lint      # Pint (kod uslubi)
./vendor/bin/sail composer format
```

Har bir o'zgarishdan keyin testlar o'tishi kerak. Kod uslubi Pint bilan tekshiriladi (CI ham).

## Qoidalar

1. **Sirlar:** `.env` repoga tushmaydi. Token loglarda `***` bilan almashtiriladi.
2. **Telegram:** barcha chaqiruvlar `TelegramClient` orqali. Testlarda `Http::fake()`.
3. **Matnlar:** barcha foydalanuvchi matni `lang/uz/bot.php` da, kodda qattiq yozilmaydi.
4. **Xavfsizlik:** har bir callbackda rol va holat qayta tekshiriladi; foydalanuvchi matni chiqarishda `e()` bilan escape qilinadi; fayl yo'llari UUID asosida.
5. **Ma'lumotlar bazasi:** MySQL. Qidiruv escape qilingan `LIKE` bilan (`App\Support\LikeEscape`).
6. **Testlar:** Feature va Unit, `RefreshDatabase`. Yangi oqim uchun real webhook oqimini sinovdan o'tkazing.
7. **Tuzilma:** yangi tugma/matn oqimi uchun handler yarating (`app/Telegram/Handlers`), biznes qoida `app/Services` da bo'lsin. `TelegramWebhookController` faqat kirish nuqtasi.

Tuzilma tafsiloti: `docs/ARCHITECTURE.md`. Joylash: `docs/DEPLOY.md`.
