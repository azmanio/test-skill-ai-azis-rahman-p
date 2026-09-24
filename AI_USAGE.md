# AI_USAGE.md

## Alat & model

- Tool: Hermes Agent (Nous Research)
- Model: my-power (Hermes agent runtime)

## Bagian yang dibantu AI

Arsitektur & transaction boundary, migration/schema PostgreSQL, controller/service layer, automated tests (checkout, payment, concurrency), Docker (Dockerfile, compose.yaml), dokumentasi, debugging.

## Tiga prompt penting

### 1. Arsitektur transaksi + locking

Prompt: "Rancang checkout dengan DB transaction dan row locking PostgreSQL agar aman terhadap concurrent checkout."

- AI suggestion: `lockForUpdate()` pada produk di dalam `DB::transaction`, validasi stok setelah lock, snapshot harga dari DB.
- Accepted: alur lock → validasi → snapshot → create order → decrement stock → commit.
- Changed: dipindah dari controller ke service layer (`CheckoutService`) supaya dipakai web form dan API sekaligus.
- Why: satu sumber business logic, tidak duplikasi di dua controller.

### 2. Payment idempotency + event fingerprint

Prompt: "Payment harus idempotent; event_id unik; event_id dipakai ulang dengan payload berbeda harus ditolak."

- AI suggestion: cek `PaymentEvent::where(event_id)` sebelum transaksi, simpan SHA-256 payload.
- Accepted: simpan `payload_hash` (SHA-256 ter-normalisasi) + unique constraint `event_id`.
- Changed: cek idempotency dipindah ke dalam transaction di bawah `lockForUpdate()` — jika cek dilakukan di luar transaksi, dua request event sama yang masuk bersamaan bisa terkena unique violation (500).
- Why: idempotent tetap aman meskipun dua event sama masuk bersamaan.

### 3. Test concurrency nyata

Prompt: "Buat automated test dua checkout bersamaan stok=1 → 1 sukses 1 ditolak."

- AI suggestion: pakai `pcntl_fork` dua proses PHP, masing-masing menjalankan `CheckoutService` di PostgreSQL yang sama.
- Accepted: fork + `DB::purge()` per child + exit code 0/1.
- Changed: tiap child memanggil service langsung (bukan melalui HTTP) agar tidak memerlukan server; ada sleep 50ms supaya lock benar-benar bertabrakan.
- Why: PHPUnit berjalan sequential, tanpa fork tidak bisa membuktikan race.

## Verifikasi hasil AI

- Semua diverifikasi melalui `php artisan test` — 23 test hijau (69 assertions), termasuk concurrency test dengan fork.
- Hasil lock dicek manual via psql (stok dan orders akhir setelah concurrency test).
- Beberapa bug ditemukan saat review (di bawah), bukan sekadar asumsi "AI bener saja".

## Bug yang ditemukan (saat review / testing)

1. **CSRF 419 di test web checkout** — Laravel 13 mengganti middleware CSRF (`VerifyCsrfToken` → `ValidateCsrfToken`). Fix: `$this->withoutMiddleware()` di test. Verified: test hijau.
2. **Race check event_id di luar transaction** — dua request payload sama yang concurrent bisa 500 (unique violation tidak tertangkap). Fix: re-check di dalam transaction di bawah lock. Verified: test duplicate idempotent + event reuse hijau.
3. **Response shape salah** — service mengembalikan `[0 => 'accepted']` bukan `['status' => 'accepted']`. Fix: `return ['status' => ...]`. Verified: `valid_payment_accepted` hijau.
4. **Test suite menghapus data DB aplikasi** — `DB_DATABASE` di `compose.yaml environment:` meng-override `phpunit.xml` (env proses menang atas `<env force>`), sehingga `RefreshDatabase` migrate:fresh berjalan di DB app (`jualemas`), bukan test DB. Gejala: katalog kosong setelah test. Fix: hapus `DB_DATABASE` dari compose `environment:` — app mengambil dari `.env`, test dari phpunit.xml (`jualemas_test`). Verified: PROBE_DB=jualemas_test + data app utuh setelah test.
5. **Catch exception salah class** — controller menangkap `Illuminate\Http\Exceptions\HttpException`, tapi service melempar `Symfony\Component\HttpKernel\Exception\HttpException` → web checkout stok kurang menampilkan stack trace. Fix: catch class Symfony. Verified: redirect "Stok tidak mencukupi" tanpa stack trace.
6. **SweetAlert tidak muncul untuk qty invalid** — validasi HTML native (`min=1`, `required`) memblokir submit sebelum handler JS berjalan (form tidak pernah dispatch submit event). Fix: `novalidate` di form + validasi client-side sendiri. Verified: toast muncul untuk qty=0 dan qty>stok.

## Hal yang masih belum yakin

- Concurrency test memakai fork lokal, bukan simulasi beban tinggi. Locking-nya sama dengan produksi (row lock PostgreSQL), sehingga tidak ada risiko overselling — tetapi throughput memang bukan scope.
- Simulasi lokal: tanpa payment gateway sungguhan, tanpa auth user, tanpa retry policy.

## Data privacy

Hanya data fiktif dari brief. Tidak ada kredensial nyata, data pelanggan, atau kode perusahaan sebelumnya. Token payment `local-test-token` (development only).