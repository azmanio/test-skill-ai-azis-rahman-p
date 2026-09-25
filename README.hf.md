---
title: Jualemas — Mini Checkout Emas
emoji: 💰
colorFrom: gold
colorTo: yellow
sdk: docker
app_port: 80
pinned: false
license: mit
---
# Jualemas — Mini Checkout Emas (Test Skill)

Aplikasi Laravel 13 (katalog → checkout → mock payment) di-deploy sebagai **Docker Space**.

## Cara pakai (pengguna)

1. Buka Space ini (URL di kanan atas).
2. Root redirect ke **/catalog** — daftar produk emas.
3. Klik produk dengan stok → pilih jumlah → **Checkout**.
4. Order masuk status `pending`, stok berkurang (row lock PostgreSQL).
5. Bayar via **API mock payment** (di bawah).

## API mock payment

```
POST /api/mock-payments
Header: X-Payment-Token: <PAYMENT_TOKEN>
Body: {"event_id":"evt-001","order_number":"ORD-...","amount":1500000,"status":"paid"}
```

- Token salah → `400 rejected`
- Event sama dikirim ulang → `already_processed`
- Nominal beda → `400 rejected`

## Environment (di-set HF Spaces → Settings)

| Var | Contoh |
|---|---|
| `APP_KEY` | `base64:...` (dari `php artisan key:generate`) |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | host Neon |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `jualemas` |
| `DB_USERNAME` | user Neon |
| `DB_PASSWORD` | password Neon |
| `DB_SSLMODE` | `require` |
| `PAYMENT_TOKEN` | token mock payment lu |
| `APP_URL` | URL Space |

Entrypoint otomatis: tunggu DB → `migrate` → `db:seed` → start nginx+php-fpm.