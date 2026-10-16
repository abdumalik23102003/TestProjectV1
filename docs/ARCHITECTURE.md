# Arxitektura

## So'rov yo'li

```
Telegram → POST /api/telegram/webhook
        → VerifyTelegramSecret (middleware: sir kaliti, hash_equals)
        → TelegramWebhookController (update_id takrorini Cache::add bilan atomik tekshiradi)
        → UpdateDispatcher
```

`UpdateDispatcher` tartibi:

1. Bloklangan foydalanuvchi to'xtatiladi (barcha sessiyalari tozalanadi).
2. `/start [token]` → `StartHandler`
3. `/help`
4. Ro'yxatdan o'tish sessiyasi (`enter_name`) → `RegistrationHandler`
5. Fayl → `FileUploadHandler`
6. Matn: yuklash wizardi → `UploadInputHandler`, qidiruv → `SearchInputHandler`
7. Tugma (callback) → `Router`

## Callback formati

`namespace:amal:id:qo'shimcha`, masalan `admin:user_block_do:12`.

| Namespace | Handler |
|---|---|
| `role:*` | RoleHandler |
| `menu:*`, `confirm:*`, `approve:*` | MenuHandler |
| `teacher:*`, `material:*`, `upload:*` | TeacherHandler |
| `student:*` | StudentHandler |
| `admin:*` | AdminHandler |
| `help:*` | HelpHandler |

Har bir handler rol va holatni o'zi qayta tekshiradi (tugmani yashirish xavfsizlik emas).

## Katlamlar

- `app/Telegram/`: Telegram bilan bog'liq kod (client, router, dispatcher, handlerlar, menyular).
- `app/Services/`: biznes qoidalar (ro'yxatdan o'tish, ulanish, qidiruv, audit, fayl saqlash, ruxsat).
- `app/Http/Controllers/TeacherController`, `StudentController`: yuklash va yuklab olish mantig'i.
- `lang/uz/bot.php`: barcha foydalanuvchi matnlari.

## Holatlar

| Jadval | Holatlar |
|---|---|
| `users.status` | `active`, `pending`, `rejected`, `blocked` |
| `users.role` | `teacher`, `student`, `admin` |
| `teacher_students.status` | `pending`, `active`, `rejected`, `revoked` |
| `materials.status` | `draft`, `active`, `archived` |
| `materials.visibility` | `public`, `private` |

Qoidalar:
- Foydalanuvchi akkaunti ism kiritilgandan keyingina yaratiladi.
- Ustoz `pending` holatida admin tasdiqlashini kutadi. Admin va `ADMIN_TELEGRAM_IDS` dagilar darhol `active`.
- Admin o'zini yoki boshqa adminni bloklay olmaydi. Bloklash tasdiq bilan bajariladi.
- Blokdan chiqarilganda hech qachon tasdiqlanmagan ustoz `pending` ga qaytadi.
- Bloklangan yoki tasdiqlanmagan ustozning materiallari qidiruvda ko'rinmaydi.

## Fayllar

- Diskda faqat UUID nom ishlatiladi (`YYYY/MM/DD/<uuid>.<ext>`); foydalanuvchi bergan nom faqat ko'rsatish uchun saqlanadi.
- Yuklash: `pending_<uuid>` vaqtinchalik fayl → tasdiqlanganda doimiy nusxa → bazaga tranzaksiyada yozish → vaqtinchalik fayl o'chadi.
- `php artisan materials:cleanup` (soatiga bir marta) tugallanmagan yuklashlarning `pending_*` fayllarini, eski arxiv materiallarini va muddati o'tgan versiyalarni tozalaydi.

## Xavfsizlik qarorlari

- Webhook sirisiz faqat `local` va `testing` muhitida ishlaydi (boshqa muhitda 403).
- Bot tokeni loglarda `***` bilan almashtiriladi.
- Ismlar xom holda saqlanadi (harf, raqam, probel va `. ' ’ ‘ ʻ ʼ -` ruxsat etiladi), chiqarishda `e()` bilan escape qilinadi.
- Taklif tokeni ochiq saqlanadi (havola qayta ko'rsatilishi uchun). Token faqat *so'rov* yaratadi, ulanishni ustoz tasdiqlaydi.
- Qidiruv escape qilingan `LIKE` bilan ishlaydi. InnoDB FULLTEXT ataylab ishlatilmagan: u tranzaksiya yopilmaguncha yangi qatorlarni ko'rmaydi va qisqa so'zlarni topmaydi.
