# Mini Checkout Emas — Jualemas (Test Skill AI)

Aplikasi simulasi katalog → checkout → reservasi stok → pending order → mock payment → paid order. Dibuat untuk test skill full stack (brief JEINDO / JUALEMAS.ID).

## Stack

Laravel 13 · PHP 8.4 · Blade · PostgreSQL 17 · Docker Compose · PHPUnit

## Requirements

Docker Engine + Docker Compose v2. PHP dan Composer tidak perlu terpasang di host — semua berjalan di dalam container.

## Docker setup

```bash
cp .env.example .env
docker compose exec app php artisan key:generate
docker compose up -d
```

Container: `jualemas-app` (Laravel) dan `jualemas-postgres` (PostgreSQL 17). Compose menunggu postgres sehat (`pg_isready`) sebelum app mulai.

Setelah up, jalankan migrasi dan seed:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Check container

```bash
docker compose ps
```

### Logs

```bash
docker compose logs -f app
docker compose logs -f postgres
```

### Data seed (sesuai brief)

| Produk         | Harga satuan | Stok |
| -------------- | -----------: | ---: |
| Antam 1 gram    | 1.500.000    |    1 |
| UBS 1 gram      | 1.450.000    |    3 |
| Emasku 0.5 gram |   750.000    |    0 |

Seeder idempotent (`updateOrCreate`), aman dijalankan ulang.

### Run application

```
http://localhost:8000
```

Root redirect ke katalog. Checkout web redirect ke halaman detail order.

### Run tests

```bash
docker compose exec app php artisan test
```

Test memakai database PostgreSQL terpisah (`jualemas_test`), bukan database aplikasi. Isolasi memakai `RefreshDatabase` / `DatabaseMigrations`. Termasuk test concurrency: dua checkout bersamaan di stok 1 → hanya satu yang berhasil, satu ditolak, stok menjadi 0.

## Mock payment

`POST /api/mock-payments`, wajib header `X-Payment-Token` (nilai dari `PAYMENT_TOKEN` di `.env` — contoh: `local-test-token`). Ganti `ORD-...` dengan nomor order hasil checkout.

```bash
# Payment berhasil
curl -X POST http://localhost:8000/api/mock-payments \
  -H "X-Payment-Token: local-test-token" -H "Content-Type: application/json" \
  -d '{"event_id":"evt-001","order_number":"ORD-...","amount":1500000,"status":"paid"}'
# {"status":"accepted"}

# Event sama dikirim ulang
# {"status":"already_processed"}

# Token salah
curl -X POST http://localhost:8000/api/mock-payments \
  -H "X-Payment-Token: wrong" -H "Content-Type: application/json" \
  -d '{"event_id":"evt-002","order_number":"ORD-...","amount":1500000,"status":"paid"}'
# 400 {"status":"rejected","message":"Invalid or missing payment token"}

# Nominal salah
curl -X POST http://localhost:8000/api/mock-payments \
  -H "X-Payment-Token: local-test-token" -H "Content-Type: application/json" \
  -d '{"event_id":"evt-003","order_number":"ORD-...","amount":1000,"status":"paid"}'
# 400 {"status":"rejected","message":"Invalid amount"}

# Event ID dipakai ulang dengan payload berbeda
curl -X POST http://localhost:8000/api/mock-payments \
  -H "X-Payment-Token: local-test-token" -H "Content-Type: application/json" \
  -d '{"event_id":"evt-001","order_number":"ORD-...","amount":999999,"status":"paid"}'
# 400 {"status":"rejected","message":"Event ID reused with different payload"}
```

## Deploy (Render)

Pakai Render Blueprint — container app + Postgres free sekaligus:

1. Fork/push repo ini ke GitHub.
2. Buka https://dashboard.render.com/blueprints → **New Blueprint Instance** → connect repo.
3. Render baca `render.yaml` → buat service `jualemas` (Docker) + DB `jualemas-db` (Postgres) otomatis.
4. **Set `PAYMENT_TOKEN`** di dashboard service → Environment (Render tanya karena `sync: false`). Contoh: `rahasia-token-produksi`.
5. Deploy otomatis jalan; preDeployCommand menjalankan `migrate` + `db:seed` sebelum app start.
6. Buka URL service (`https://jualemas.onrender.com`).

Catatan:
- Free tier: idle 15 menit → sleep, cold start 30–60 detik. Cocok demo/test.
- DB free Render **expired 30 hari** — untuk demo singkat cukup. Kalau mau permanen: pakai Neon Postgres + set `DB_*` manual.
- `php artisan serve` (di Dockerfile) single-threaded — cukup untuk test skill, bukan produksi tinggi.

## Work time

```text
Menerima soal: 24 Sep 2026, 10:12 WIB
Mulai proses:  12:28 WIB (istirahat siang)
Selesai:       21:30 WIB, 24 Sep 2026
Durasi aktif:  ± 4-5 jam
```

Brief menargetkan 3 jam; deadline dari HC 25 Sep 12:00 WIB.

## Assumptions

- Simulasi lokal, tanpa payment gateway sungguhan.
- Token payment hanya untuk development.
- `docker compose down` menghentikan container tanpa menghapus data; `down -v` ikut menghapus volume `postgres_data`.

## Limitations

- Tidak ada login, keranjang multiproduk, expiry/cancel order, atau halaman kelola stok — semua di luar scope brief (brief hanya mensyaratkan stok berkurang saat checkout dan tidak pernah negatif).
- Tidak ada retry otomatis saat deadlock — mengandalkan locking PostgreSQL.
- Feedback memakai SweetAlert2 via CDN (fallback `alert()` native jika offline); qty divalidasi di klien, tetapi keputusan akhir tetap di server.

## Concurrency strategy

- Checkout: `DB::transaction` + `lockForUpdate()` pada row produk (`SELECT ... FOR UPDATE`). Lock dipegang sampai commit/rollback; request kedua menunggu lock, lalu melihat stok sisa dan ditolak (409) jika kurang. Check constraint `products.stock >= 0` menjadi pengaman terakhir.
- Payment: `lockForUpdate()` pada row order; `event_id` unique; `payload_hash` (SHA-256) untuk membedakan event sama-payload sama (→ `already_processed`) dengan event sama-payload berbeda (→ ditolak). Stok tidak dikurangi saat payment — sudah di-reserve ketika checkout.