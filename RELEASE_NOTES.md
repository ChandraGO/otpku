# KodeOTP Platform v1.0.0

Tanggal rilis: 2 Agustus 2026

## Isi rilis

- Laravel 13 + Blade + Tailwind CSS 4 + Alpine.js.
- Registrasi, login email/username, OTP email, reset password, profil, dark/light mode.
- Katalog dan seluruh lifecycle order OTP berbasis API partner SMS Virtuals.
- Wallet dan ledger idempoten, markup harga, refund, laporan, dan adjustment admin.
- Top up Pakasir dengan create transaction, checkout/QR/VA, polling, webhook, dan verifikasi Transaction Detail sebelum saldo dikreditkan.
- Pengumuman user/admin, SMTP runtime, pengaturan provider, backup/upload/restore database, audit log.
- PostgreSQL, Redis queue/session/cache, Docker multi-stage, dan deploy blue/green.
- Gateway internal hanya pada loopback `127.0.0.1:3280`; tidak mengambil port publik 80/443.
- Workflow GitHub Actions untuk update VPS.

## Ketahanan transaksi

Order lokal dibuat dan saldo didebit secara atomik sebelum job provider dikirim. Job memakai `Idempotency-Key`, retry bertahap, pemulihan terjadwal, dan reference ledger unik. Error provider 4xx yang permanen mengembalikan saldo satu kali; error jaringan/5xx tetap berstatus `provider_pending` dan dapat di-retry otomatis atau manual dari admin.

## Batas validasi rilis

Paket telah melewati pemeriksaan sintaks PHP, shell, JavaScript, JSON/YAML, directive Blade, route reference, import class internal, secret/debug scan, dan integritas ZIP. Uji live ke SMS Virtuals, SMTP, dan Pakasir tidak dijalankan karena credential pengguna tidak disertakan. Build dependency Composer/NPM juga harus dijalankan oleh Docker pada VPS yang memiliki akses internet.
