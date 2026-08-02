# KodeOTP Platform

Marketplace penerimaan OTP berbasis **Laravel 13, Blade, Tailwind CSS 4, Alpine.js, PostgreSQL, Redis, SMS Virtuals, dan Pakasir**. Tema awal dark, light mode tersedia, tampilan responsif, halaman publik SEO-friendly, serta panel admin lengkap.

> Gunakan layanan hanya untuk aktivitas yang sah dan sesuai ketentuan aplikasi tujuan. Sistem ini tidak ditujukan untuk penipuan, penyamaran, pengambilalihan akun, spam, atau upaya menghindari pembatasan platform.

## Fitur utama

- Registrasi username, nama, WhatsApp, email, password, dan konfirmasi password.
- OTP email untuk verifikasi akun dan reset password; expiry serta resend dapat diatur admin.
- Login dengan email atau username, throttling, status suspend/ban, session Redis.
- Katalog layanan, negara, operator, stok, success rate, modal provider, dan harga jual.
- Markup persentase, biaya tetap, dan pembulatan harga otomatis. Contoh modal Rp5.000 + 10% menjadi Rp5.500.
- Wallet dengan ledger immutable, idempotency key, debit order, refund, top up, dan adjustment admin.
- Order OTP: placement melalui queue dengan idempotency key, retry otomatis hingga 7 hari untuk gangguan sementara, polling status, ready, resend, cancel, complete, reactivate, expiry, dan refund sesuai status.
- Pakasir: create payment, checkout/QR/VA, polling, webhook, verifikasi ulang detail transaksi, dan proteksi kredit ganda.
- Pengumuman user/admin, jadwal tayang, pin, dan kategori.
- Admin: dashboard, user/saldo, order, top up, laporan CSV, SMTP, API keys, harga, backup/upload/download/restore database.
- Secret setting dienkripsi dengan `APP_KEY`; request provider hanya dari backend.
- Blue/green deployment melalui gateway Caddy internal di loopback, tanpa mengambil port publik 80/443.

## Integrasi SMS Virtuals

`App\Services\SmsVirtualClient` menyediakan wrapper untuk endpoint yang didokumentasikan:

- Balance, balance history, dan profile.
- Deposit rate, history, methods, request, dan cancel.
- Countries, operators, services, service list per country.
- Order history, activation history, ongoing activation.
- Request single service, get status, ready, resend, cancel, dan complete.
- Compatibility flow: multi-service, reactivate, dan service countries.

API key dikirim sebagai header `x-api-key` oleh server. Browser hanya memanggil route Laravel milik aplikasi dan tidak pernah menerima API key provider.

## Integrasi Pakasir

Top up dibuat dari backend melalui Transaction Create. Webhook **tidak langsung menambah saldo**. Aplikasi selalu meminta Transaction Detail dan mencocokkan project, order ID, amount, serta status sebelum membuat wallet credit dengan reference key unik.

Webhook yang diisi pada dashboard Pakasir:

```text
https://DOMAIN-ANDA/webhooks/pakasir
```

Webhook opsional SMS Virtuals:

```text
https://DOMAIN-ANDA/webhooks/sms-virtual/SECRET-PANJANG
```

## Persyaratan VPS

- Linux dengan Docker Engine dan Docker Compose plugin.
- Git, OpenSSL, curl, `flock`/util-linux, `ss`/iproute2, dan Caddy utama yang sudah menangani 80/443.
- DNS subdomain mengarah ke VPS.
- Port loopback kosong, default `127.0.0.1:3280`.

Stack ini **tidak mem-publish database, Redis, PHP-FPM, atau port publik**. Satu-satunya bind host adalah gateway internal `127.0.0.1:3280`.

## Instalasi pertama

Clone repository ke folder terpisah dari dua service lama:

```bash
sudo mkdir -p /opt/kodeotp
sudo git clone https://github.com/USERNAME/REPOSITORY.git /opt/kodeotp/app
cd /opt/kodeotp/app
```

Atau untuk instalasi pertama dari ZIP rilis:

```bash
sudo mkdir -p /opt/kodeotp/app
sudo unzip kodeotp-laravel-ready-v1.0.0.zip -d /opt/kodeotp/tmp
sudo cp -a /opt/kodeotp/tmp/kodeotp-platform/. /opt/kodeotp/app/
cd /opt/kodeotp/app
```

Setelah aplikasi stabil, masukkan folder tersebut ke repository GitHub agar update berikutnya dapat memakai workflow yang disertakan.

Jalankan installer dengan domain sendiri:

```bash
sudo KODEOTP_DOMAIN=otp.domainanda.com bash deploy/install.sh /opt/kodeotp/app
```

Bila port 3280 sudah dipakai:

```bash
sudo KODEOTP_DOMAIN=otp.domainanda.com \
  KODEOTP_GATEWAY_PORT=3281 \
  bash deploy/install.sh /opt/kodeotp/app
```

Installer membuat:

- `/opt/kodeotp/.env`: domain, loopback gateway, dan credential PostgreSQL.
- `/opt/kodeotp/app.env`: konfigurasi Laravel dan secret runtime.
- `/usr/local/bin/kodeotp-update`: perintah update blue/green.
- Admin awal dengan password acak yang hanya ditampilkan saat instalasi.

## Routing Caddy utama tanpa bentrok

Jangan mengubah Caddy internal milik REST API/payment yang sudah ada. Tambahkan site baru pada **Caddy utama VPS**:

```caddy
otp.domainanda.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3280
}
```

Lalu validasi dan reload Caddy utama sesuai layout VPS Anda, misalnya:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

Panduan khusus untuk pola VPS pada arsip referensi tersedia di `deploy/EXISTING-VPS-CADDY.md`.

Cek routing:

```bash
sudo bash /opt/kodeotp/app/deploy/check-routing.sh
```

## Konfigurasi setelah login admin

1. Buka **Admin → Pengaturan → SMTP**, isi host, port, username, password, alamat dan nama pengirim, simpan, lalu kirim email uji.
2. Buka tab **SMS Virtual**, isi base URL dan API key, tes saldo, lalu sinkronkan katalog.
3. Buka tab **Pakasir**, isi project slug dan API key. Metode yang tersedia mengikuti dokumentasi Pakasir; nilai awal adalah `qris`.
4. Atur minimum/maksimum top up, markup, fixed fee, pembulatan, expiry, dan kebijakan refund.
5. Ganti email, WhatsApp, dan password admin awal dari profil.

## Update manual

```bash
cd /opt/kodeotp/app
sudo git fetch --prune origin
sudo git reset --hard origin/main
sudo APP_DIR=/opt/kodeotp/app STACK_DIR=/opt/kodeotp /usr/local/bin/kodeotp-update
```

Update script akan:

1. membangun image baru;
2. menjalankan migrasi sebelum switch;
3. menyalakan slot inactive;
4. menunggu `/healthz` sehat;
5. mengganti upstream Caddy internal;
6. reload Caddy;
7. mengganti worker dan scheduler;
8. menghentikan slot lama.

## Deploy otomatis GitHub Actions

Tambahkan repository secrets:

- `VPS_HOST`
- `VPS_USER`
- `VPS_PORT`
- `VPS_SSH_KEY`

Workflow `.github/workflows/deploy.yml` tidak berisi IP publik hardcoded. Push ke `main` atau `master` menjalankan update di `/opt/kodeotp/app`.

## Operasional

```bash
# Status seluruh container
sudo docker compose --env-file /opt/kodeotp/.env -p kodeotp \
  -f /opt/kodeotp/docker-compose.yml ps

# Log slot aktif
ACTIVE=$(cat /opt/kodeotp/.active_color)
sudo docker compose --env-file /opt/kodeotp/.env -p kodeotp \
  -f /opt/kodeotp/docker-compose.yml logs -f "app_$ACTIVE"

# Log worker
sudo docker compose --env-file /opt/kodeotp/.env -p kodeotp \
  -f /opt/kodeotp/docker-compose.yml logs -f worker

# Update aplikasi
sudo /usr/local/bin/kodeotp-update
```

## Backup dan restore

Backup otomatis dijadwalkan setiap hari pukul 03:30 zona aplikasi. Admin juga dapat membuat dan mengunduh `.sql.gz` manual. Restore memerlukan kata `RESTORE` dan password admin.

Sebelum restore produksi, buat snapshot VPS/volume tambahan dan pertahankan `APP_KEY` lama di `/opt/kodeotp/app.env`, karena setting rahasia dan payload sensitif pada backup dienkripsi dengan key tersebut. Restore database mengganti data saat ini dan dapat membatalkan session aktif.

## Catatan keamanan DevTools

Tidak mungkin menyembunyikan bahwa browser mengakses route aplikasi sendiri; URL dan response frontend selalu dapat diperiksa pengguna. Implementasi ini menyembunyikan bagian yang memang harus rahasia:

- tidak ada API key/provider URL sensitif di JavaScript atau HTML;
- seluruh koneksi SMS Virtuals dan Pakasir berjalan server-to-server;
- setting rahasia terenkripsi;
- response order hanya memuat field yang diperlukan user;
- webhook pembayaran diverifikasi ulang ke provider;
- database/Redis tidak dipublish;
- gateway hanya bind ke loopback;
- rate limit, CSRF, secure cookie, audit log, ledger idempotent, dan security headers aktif.

## Pengembangan lokal

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
php artisan queue:work
php artisan schedule:work
```

## Validasi sebelum produksi

Credential provider tidak disertakan dalam repository. Karena itu, setelah deploy lakukan tes nyata dengan akun sandbox/nominal kecil untuk:

- pengiriman email OTP;
- sinkronisasi katalog;
- satu order OTP hingga complete/cancel/refund, termasuk simulasi gangguan worker lalu retry;
- satu top up Pakasir hingga saldo terkredit;
- backup lalu restore pada database staging.
