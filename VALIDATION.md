# Laporan validasi rilis

Tanggal: 2 Agustus 2026 (Asia/Makassar)

## Lolos

- 78 file PHP: `php -l`.
- Seluruh script `deploy/*.sh`: `bash -n`.
- `resources/js/app.js` dan `vite.config.js`: `node --check`.
- `composer.json` dan `package.json`: parse JSON.
- workflow GitHub Actions dan Docker Compose: parse YAML.
- Keseimbangan directive utama pada semua Blade view.
- Semua referensi route aplikasi terpetakan, termasuk resource announcement admin.
- Semua import class internal `App\\...` memiliki file sumber.
- Tidak ditemukan private key, API key terisi, atau debug dump pada source aplikasi.
- Caddy internal hanya bind host loopback melalui Compose.

## Perlu diuji setelah credential diisi

- Pengiriman OTP SMTP dan reset password.
- Sinkronisasi katalog serta saldo SMS Virtuals.
- Order sukses, resend, cancel, expiry, provider refund, dan retry gangguan jaringan.
- Top up Pakasir sandbox/nominal kecil, webhook, polling, dan proteksi kredit ganda.
- Backup/restore pada staging sebelum digunakan di produksi.

## Keterbatasan lingkungan build

Dependency Composer/NPM tidak disertakan dalam ZIP. Lingkungan penyusunan tidak memiliki Composer/Docker daemon dan registry NPM tidak dapat dijangkau, sehingga smoke test runtime penuh tidak dijalankan di sini. Dockerfile multi-stage akan mengunduh serta membangun dependency saat deploy di VPS.
