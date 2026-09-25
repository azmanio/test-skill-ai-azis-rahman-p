# Mini Checkout Emas — Jualemas

Aplikasi Laravel untuk alur katalog, checkout, reservasi stok, dan mock payment. Implementasi mengikuti brief JEINDO/JUALEMAS.ID.

## Teknologi

- Laravel 13 dan PHP 8.4
- Blade
- PostgreSQL 17
- Docker Compose
- PHPUnit 12

## Menjalankan Lokal

Host hanya membutuhkan Docker Engine dan Docker Compose v2.

```bash
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps --user "$(id -u):$(id -g)" \
  --entrypoint php app artisan key:generate --force
docker compose up -d
```

Buka `http://localhost:8000`. Container `jualemas-app` menjalankan migration. Untuk database kosong, jalankan `docker compose exec app php artisan db:seed --force` satu kali setelah container siap. `SEED_DATABASE` sengaja tidak diaktifkan secara default agar restart tidak mengembalikan stok demo.

Perintah operasional:

```bash
docker compose ps
docker compose logs -f app
docker compose exec app php artisan test
```

Untuk mulai dari database kosong:

```bash
docker compose exec app php artisan migrate:fresh --seed --force
```

Perintah tersebut menghapus isi database. Jangan menjalankannya pada database yang berisi order yang perlu disimpan.

```bash
docker compose down
```

Perintah tersebut menghentikan container dan mempertahankan volume PostgreSQL. Untuk menghapus database beserta datanya:

```bash
docker compose down -v
```

## Data Awal

| Produk | Harga | Stok |
| --- | ---: | ---: |
| Antam 1 gram | Rp1.500.000 | 1 |
| UBS 1 gram | Rp1.450.000 | 3 |
| Emasku 0.5 gram | Rp750.000 | 0 |

Seeder berjalan eksplisit dan menggunakan `updateOrCreate`. Karena itu, `db:seed` menulis ulang harga dan stok demo. Gunakan `migrate:fresh --seed` hanya saat memang ingin mengembalikan data demo ke keadaan awal.

## Endpoint

### `POST /api/checkout`

```json
{
  "product_id": 1,
  "quantity": 1
}
```

Respons `201` berisi order dan snapshot harga. `product_id` atau quantity yang tidak valid menghasilkan `422`. Stok tidak cukup menghasilkan `409`.

### `POST /api/mock-payments`

Header wajib: `X-Payment-Token`.

```json
{
  "event_id": "evt-001",
  "order_number": "ORD-...",
  "amount": 1500000,
  "status": "paid"
}
```

Event pertama untuk order pending menghasilkan `accepted`. Event identik berikutnya menghasilkan `already_processed`. `event_id` yang sama dengan payload berbeda ditolak. Nominal dan status harus sesuai dengan order.

Contoh:

```bash
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: local-test-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-001","order_number":"ORD-...","amount":1500000,"status":"paid"}'
```

## Contoh cURL

Gunakan `ORDER_NUMBER` dari respons checkout dan sesuaikan `EVENT_ID` serta nominal dengan order tersebut.

```bash
# Payment berhasil
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: local-test-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-001","order_number":"ORDER_NUMBER","amount":1500000,"status":"paid"}'
# {"status":"accepted"}

# Event yang sama dikirim ulang
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: local-test-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-001","order_number":"ORDER_NUMBER","amount":1500000,"status":"paid"}'
# {"status":"already_processed"}

# Token salah
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: wrong-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-002","order_number":"ORDER_NUMBER","amount":1500000,"status":"paid"}'
# 400 {"status":"rejected","message":"Invalid or missing payment token"}

# Nominal salah
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: local-test-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-003","order_number":"ORDER_NUMBER","amount":1000,"status":"paid"}'
# 400 {"status":"rejected","message":"Invalid amount"}

# Event ID sama dengan payload berbeda
curl -X POST http://localhost:8000/api/mock-payments \
  -H 'X-Payment-Token: local-test-token' \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-001","order_number":"ORDER_NUMBER","amount":999999,"status":"paid"}'
# 400 {"status":"rejected","message":"Event ID reused with different payload"}
```

## Aturan Transaksi

- `CheckoutService` mengambil harga dari database; harga pada request client diabaikan.
- Product dibaca dengan `lockForUpdate()` di dalam `DB::transaction()`. Dua checkout bersamaan tidak dapat melewati pemeriksaan stok yang sama.
- Order menyimpan `checkout_price` dan `total` sebagai snapshot.
- Stok dikurangi saat checkout, bukan saat payment.
- `products.stock >= 0` juga dijamin oleh database constraint.
- `PaymentService` mengunci order, memeriksa nominal, lalu menyimpan event payment.
- `payment_events.event_id` unik. SHA-256 payload membedakan retry yang sama dari penyalahgunaan event ID.
- Payment untuk order yang sudah paid ditolak.

## Demo HTTPS Lokal

Cloudflare Quick Tunnel dapat digunakan untuk menunjukkan aplikasi selama laptop, Docker, dan `cloudflared` tetap berjalan:

```bash
cloudflared tunnel --url http://127.0.0.1:8000
```

Set `APP_URL` ke URL yang dicetak tunnel sebelum menjalankan container. Tunnel memakai domain acak `trycloudflare.com` dan tidak mempunyai SLA. Kegagalan atau penghentian proses akan menghentikan akses publik.

Quick Tunnel hanya digunakan untuk demo, bukan deployment produksi.

## Deploy Persisten

`render.yaml` menyediakan konfigurasi Docker Web Service gratis. PostgreSQL harus berasal dari provider terpisah, misalnya Neon. Jangan membuat Render Postgres dari blueprint ini.

Isi nilai berikut melalui dashboard Render:

- `PAYMENT_TOKEN`
- `DB_URL`, yaitu connection string PostgreSQL dari provider DB. Sertakan parameter `sslmode=require` di URL tersebut.
- `APP_URL`, jika dashboard tidak menetapkannya otomatis
- `TRUSTED_PROXIES=*`, karena Render meneruskan request melalui proxy internal

Set `SEED_DATABASE=false` setelah seeding awal. Jalankan `php artisan db:seed --force` secara manual bila membutuhkan data demo.

Nginx memakai variabel `PORT` jika tersedia dan default `80` pada provider lain. `APP_DEBUG` harus bernilai `false`.

Belum ada deployment persisten ke provider yang dijalankan dalam verifikasi repository ini. Konfigurasi lokal dan alur HTTPS melalui Quick Tunnel yang telah diuji.

## Test

```bash
docker compose exec app php artisan test
```

Database test adalah `jualemas_test`. Database aplikasi `jualemas` tidak digunakan oleh `RefreshDatabase`. Test mencakup:

- validasi request;
- ignorasi harga client;
- price snapshot;
- reservasi stok;
- payment idempotency;
- penolakan nominal/status yang salah;
- dua proses checkout bersamaan pada stok satu.

## Asumsi dan Batasan

- Payment adalah simulasi; tidak ada integrasi payment gateway.
- Tidak ada autentikasi, keranjang multibproduk, atau halaman pengaturan stok.
- Stok direservasi ketika checkout dan tidak dikembalikan ketika order pending dibatalkan secara otomatis karena fitur tersebut di luar scope.
- Test concurrency memakai proses fork lokal, bukan uji beban.
- Quick Tunnel tidak cocok untuk production atau SLA.

## Waktu Pengerjaan

- Brief diterima sekitar 24 September 2026 pukul 10:12 WIB.
- Pengerjaan dimulai sekitar 12:28 WIB, saat istirahat siang.

Durasi aktif tidak dicatat sebagai estimasi presisi dalam repository.
