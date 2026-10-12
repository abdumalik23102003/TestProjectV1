# Telegram Kutubxona Boti — AI Coding Guidelines

**Stack:** Laravel 13, PHP 8.3, MySQL 8.4, Docker Sail, Telegram Bot API

## Development Loop

```bash
# Fresh start (har yangi feature'dan keyin)
./vendor/bin/sail artisan migrate:fresh --seed

# Run tests
./vendor/bin/sail artisan test

# Lokal bot testing (development only)
./vendor/bin/sail artisan telegram:poll
```

## Qoidalar

1. **Sirlarni commit qilma:** `.env` hech qachon repo'ga tushmaydi. Token faqat runtime'da environment'dan olinadi.

2. **Tashqi dasturlarni `curl | bash` bilan o'rnatma:** Hammasi `composer.json` va migrations orqali. Docker va Sail o'rnatilgan deb faraz qil.

3. **Barcha matn O'zbek tilida:** `lang/uz/bot.php` faylida bo'lsin, kodda hardkod bo'lmasin.

4. **Test qoida:** Telegram API chaqiruvlari `Http::fake()` bilan mock qilinadi. Hech qachon jonli API'ga request o'tmasin.

5. **Ma'lumotlar bazasi:** MySQL'da migrations qaydnomalari, SQLite zaxira uchun `LIKE` qidiruv.

6. **Xavfsizlik:** Webhook secret tekshiruvi, token maskalanishi, HTML-escape, SHA256 checksumlar, role/holat tekshiruvi har callbackda.

7. **Har bosqichdan keyin:**
   - `./vendor/bin/sail artisan migrate:fresh --seed` xatosiz o'tsin
   - `./vendor/bin/sail artisan test` xatosiz o'tsin
   - `docs/PROGRESS.md` ga qisqa hisobot yoz

## Tech Stack Details

- **Database:** MySQL 8.4 (default Sail)
- **Queue:** `sync` (MVPda queue worker yo'q)
- **Cache:** database
- **Disk Storage:** local, doimiy volume'da `/var/www/storage/private_materials`
- **Telegram API:** Remote mode (`https://api.telegram.org`)
- **Testing:** Laravel Pest (default)

## Deploy

Hujjatlarni `docs/DEPLOY.md` dan qo'l: hostingga bog'liq bo'lmagan Dockerfile va env template.

---

**Started:** Oct 12, 2026
