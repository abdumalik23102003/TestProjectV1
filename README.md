# Telegram Kutubxona Boti

Ustozlar va o'quvchilar uchun ta'lim materiallarini Telegram orqali xavfsiz saqlash, ulashish va qidirish boti.

## Imkoniyatlar

- Uch rol: **ustoz**, **o'quvchi**, **admin**. Ustoz admin tasdiqlagandan keyin faollashadi.
- Ro'yxatdan o'tish: rol tanlash, ism kiritish (tekshiruv bilan), shundan keyingina akkaunt yaratiladi.
- Ustozning taklif havolasi: o'quvchi havola orqali ulanish so'rovini yuboradi, ustoz tasdiqlaydi.
- Fayl yuklash wizardi: sarlavha, tavsif, kategoriya, ko'rinish (public/private), tasdiqlash.
- Fayl turi tarkibi bo'yicha tekshiriladi (kengaytma emas), hajm va kunlik/umumiy kvotalar bor.
- Private materiallar faqat faol bog'langan o'quvchilarga ko'rinadi.
- Qidiruv: sarlavha, tavsif va fayl nomi bo'yicha (faqat faol ustozlar materiallari).
- Katta fayllar uchun vaqtinchalik imzolangan havola.
- Admin paneli: ustozlarni tasdiqlash/rad etish, bloklash (tasdiq bilan), statistika, audit jurnali.
- Audit jurnali: muhim amallar kim tomonidan qilingani saqlanadi.
- Webhook sir kaliti (`X-Telegram-Bot-Api-Secret-Token`) majburiy (production'da sirsiz ishlamaydi).

## Tez boshlash (Docker Sail)

Kompyuteringizda PHP o'rnatilgan bo'lishi shart emas, faqat Docker kerak.

```bash
./setup.sh
```

Skript `.env` yaratadi, paketlarni o'rnatadi, konteynerlarni ko'taradi va migratsiyalarni bajaradi.
Keyin `.env` faylida `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` va `ADMIN_TELEGRAM_IDS` ni to'ldiring.

Lokal sinov (webhooksiz, faqat development):

```bash
./vendor/bin/sail artisan telegram:poll
```

## Muhit o'zgaruvchilari

`.env.example` faylida barcha o'zgaruvchilar izohlangan. Asosiylari:

| O'zgaruvchi | Vazifasi |
|---|---|
| `TELEGRAM_BOT_TOKEN` | BotFather bergan token |
| `TELEGRAM_BOT_USERNAME` | Bot username (taklif havolalari uchun) |
| `TELEGRAM_WEBHOOK_URL` | `https://domen/api/telegram/webhook` |
| `TELEGRAM_WEBHOOK_SECRET` | Tasodifiy uzun satr (production'da majburiy) |
| `ADMIN_TELEGRAM_IDS` | Vergul bilan ajratilgan admin Telegram ID lari |
| `APP_MAX_FILE_SIZE_MB` | Bitta fayl hajmi chegarasi (standart 20) |
| `TEACHER_STORAGE_QUOTA_GB` | Ustoz uchun umumiy kvota (standart 5) |
| `MAX_UPLOADS_PER_DAY` | Ustoz uchun kunlik yuklash limiti |

## Testlar va kod sifati

```bash
./vendor/bin/sail artisan test        # barcha testlar
./vendor/bin/sail composer lint       # kod uslubini tekshirish (Pint)
./vendor/bin/sail composer format     # kod uslubini tuzatish
```

CI (GitHub Actions) har bir push'da testlar va Pint'ni ishga tushiradi.

## Webhook

```bash
./vendor/bin/sail artisan telegram:set-webhook
./vendor/bin/sail artisan telegram:webhook-info
```

## Hujjatlar

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): tuzilma, oqimlar va holatlar
- [docs/DEPLOY.md](docs/DEPLOY.md): production'ga joylash

## Texnologiyalar

Laravel 13, PHP 8.3+, MySQL 8.4, Telegram Bot API, Docker Sail.

## Litsenziya

MIT
