# AI Usage

## Tool dan model

- Tool: Hermes Agent (Nous Research)
- Model: my-power

## Bagian yang dibantu AI

AI juga digunakan dalam requirement analysis, implementasi, debugging, review, dokumentasi, dan persiapan deployment. Developer memeriksa hasil AI, menjalankan test, dan memverifikasi alur aplikasi secara manual.

## Prompt penting

### 1. Checkout concurrency

Prompt:

```text
Rancang checkout dengan PostgreSQL transaction dan row locking agar dua request bersamaan tidak sama-sama mengambil stok terakhir.
```

Saran AI: gunakan `DB::transaction()` dan `lockForUpdate()`, lalu validasi stok setelah lock.

Diterima: transaksi tetap di `CheckoutService`; stok baru diperiksa setelah row lock terbaca, lalu order dibuat dan stok dikurangi.

Diubah: pemeriksaan harga server-side dan snapshot ditambahkan agar request client tidak menentukan nilai transaksi.

Alasan: lock melindungi stok, sedangkan snapshot mempertahankan harga ketika katalog berubah.

Verifikasi: test checkout biasa, manipulasi harga, price snapshot, dan test concurrency menggunakan dua proses.

### 2. Payment idempotency

Prompt:

```text
Payment harus idempotent. Event identik boleh diulang, tetapi event_id yang sama dengan payload berbeda harus ditolak.
```

Saran AI: simpan `event_id` dan fingerprint payload, lalu periksa keduanya saat payment.

Diterima: `event_id` unik, payload di-hash, dan status order dikunci selama proses.

Diubah: pengecekan idempotency dipindahkan ke dalam transaksi agar request bersamaan tetap aman.

Alasan: pemeriksaan di luar transaksi tidak memberi lock yang sama pada order dan event.

Verifikasi: test payment valid, duplicate event, event ID dengan payload berbeda, order pending, order paid, token salah, dan nominal salah.

### 3. Test concurrency nyata

Prompt:

```text
Buat test dua checkout bersamaan pada stok satu; hanya satu yang boleh berhasil.
```

Saran AI: jalankan dua proses PHP terhadap PostgreSQL yang sama.

Diterima: `pcntl_fork()`, reset connection database di child process, lalu bandingkan hasil kedua proses.

Diubah: child memanggil service langsung, bukan HTTP, agar test tidak bergantung pada server yang sedang berjalan.

Alasan: ini menguji row lock dan transaksi tanpa menambah server test.

Verifikasi: satu order berhasil, satu request ditolak, dan stok akhir nol.

## Verifikasi terhadap hasil AI

Tidak semua suggestion langsung diterima. Berikut beberapa koreksi selama implementasi dan review:

1. CSRF test:
    - Gejala: test web checkout menghasilkan `419`.
    - Penyebab: test form tidak melalui browser session.
    - Perbaikan: middleware CSRF dimatikan pada test web tersebut; aplikasi runtime tetap memakai CSRF.
    - Verifikasi: test checkout web lulus.

2. Race pada payment:
    - Gejala: pemeriksaan event di luar transaksi berisiko menghasilkan unique violation saat request sama datang bersamaan.
    - Perbaikan: event diperiksa di dalam transaksi di bawah lock order.
    - Verifikasi: test duplicate payment lulus.

3. Exception class:
    - Gejala: stok kurang menampilkan stack trace dari web controller.
    - Penyebab: controller menangkap class exception yang keliru.
    - Perbaikan: gunakan `Symfony\Component\HttpKernel\Exception\HttpException`, sama dengan class yang dilempar service.
    - Verifikasi: browser menampilkan “Stok tidak mencukupi” tanpa trace.

4. Validasi quantity:
    - Gejala: validasi native HTML menahan submit sebelum handler JavaScript berjalan.
    - Perbaikan: form memakai `novalidate`; validasi client-side dan server-side tetap keduanya ada.
    - Verifikasi: quantity nol dan quantity melebihi stok ditolak.

5. Test database isolation:
    - Gejala: test dapat membaca `DB_DATABASE` dari process environment sebelum memakai `phpunit.xml`.
    - Penyebab: `force` pada elemen `<env>` saja belum menimpa seluruh jalur konfigurasi.
    - Perbaikan: nilai test dipaksa melalui elemen `<server>` dan regression test memeriksa database aktif.
    - Verifikasi: 24 test, 70 assertions; stok database aplikasi tetap sama sebelum dan sesudah suite.

6. HTTPS URL:
    - Gejala: form pada Quick Tunnel menghasilkan action HTTP.
    - Penyebab: Laravel belum mempercayai forwarded header dari proxy.
    - Perbaikan: `TRUSTED_PROXIES` dikonfigurasi pada middleware.
    - Verifikasi: form memakai action HTTPS dan checkout melalui tunnel berhasil.

7. Deployment runtime:
    - Gejala: mapping Compose menggunakan `8000:8000`, sedangkan image final menjalankan nginx dan PHP-FPM pada port 80.
    - Penyebab: mapping Compose dan image final memakai port berbeda.
    - Perbaikan: mapping Compose, port runtime, dan dokumentasi diselaraskan.
    - Verifikasi: build image dan startup image dijalankan tanpa memakai `php artisan serve`.

8. Seeder dan reset data:
    - Gejala: menjalankan `db:seed` pada setiap restart akan menulis ulang stok demo.
    - Penyebab: seeder menggunakan `updateOrCreate`, sehingga nilai awal dianggap sebagai data yang boleh dipulihkan.
    - Perbaikan: seeding dijalankan eksplisit; runtime menggunakan `SEED_DATABASE=false` setelah setup.
    - Verifikasi: `migrate:fresh --seed` mengembalikan `Antam 1`, `UBS 3`, `Emasku 0`; restart setelah reset tidak mengubah data.

## Keterbatasan dan ketidakpastian

- Test concurrency menggunakan dua proses lokal. Ini memeriksa mekanisme lock, bukan throughput produksi.
- Payment memakai simulator, bukan payment gateway sungguhan.
- Tidak ada cancellation order atau release reservasi otomatis karena fitur tersebut di luar scope.
- Quick Tunnel tidak mempunyai SLA. Link demo hanya aktif selama proses lokal berjalan.
- Deployment persisten belum dijalankan terhadap akun provider. `render.yaml` adalah konfigurasi, bukan bukti deployment berhasil.

## Privasi data

Seluruh nama produk, order, dan payment event berasal dari brief atau data uji sintetis. Tidak ada data pelanggan, credential provider, atau kode perusahaan sebelumnya dalam repository.
