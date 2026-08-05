# Pembaruan Dashboard, API Pelanggan, dan Legal Pages

## Fitur yang ditambahkan

- Header publik tanpa menu Harga, Login, dan Daftar.
- Dashboard pengguna yang lebih bersih, responsif, dan interaktif.
- Sidebar desktop dapat disembunyikan dan preferensinya disimpan di browser.
- Notifikasi sukses/error global yang lebih jelas tanpa teks "Ada data yang perlu diperbaiki".
- Statistik landing page dengan animasi count-up.
- FAQ accordion dengan animasi rotasi.
- Account Settings baru: Telegram ID, negara default, API key, rotasi key, dan permintaan penghapusan akun.
- Peninjauan permintaan penghapusan akun oleh admin.
- API pelanggan v1 untuk integrasi bot Telegram/aplikasi eksternal.
- Logo bisnis dapat dikonfigurasi admin melalui URL.
- Kebijakan Privasi dan Syarat & Ketentuan dalam Bahasa Indonesia.
- Default URL provider diselaraskan ke `https://api.sms-virtual.net`.

## Setelah upload ke server

```bash
php artisan migrate --force
php artisan optimize:clear
npm install
npm run build
php artisan optimize
```

Pastikan queue worker aktif karena pembuatan pesanan API memakai job `PlaceOtpOrder` yang sama dengan dashboard.

## Endpoint API pelanggan

Base URL: `/api/v1`

- `GET /me`
- `GET /balance`
- `GET /countries`
- `GET /services`
- `GET /prices`
- `GET /orders`
- `POST /orders`
- `GET /orders/{order}`
- `POST /orders/{order}/actions`

Semua endpoint memakai header:

```http
x-api-key: otp_live_xxxxxxxxxxxxxxxxx
Accept: application/json
```

API key provider SMS Virtual tetap berada di backend dan tidak pernah diberikan kepada pengguna.
