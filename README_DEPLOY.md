# dapetOTP - Deploy Pack

Asumsi struktur repository:

- frontend/
- backend/
- deploy/
- compose.yaml
- .github/workflows/deploy.yml

Pack ini menjalankan stack baru di 127.0.0.1:3281 agar stack lama pada 3280 dapat tetap hidup selama pengujian.

## Environment VPS

Buat `/opt/dapetotp/production.env` (chmod 600) dengan:

MONGO_ROOT_USERNAME=dapetotp
MONGO_ROOT_PASSWORD=<hex-random>
DB_NAME=dapetotp
JWT_SECRET=<hex-random>
FRONTEND_URL=https://dapetotp.jagoanproject.com
ADMIN_EMAIL=<email-admin>
ADMIN_PASSWORD=<password-admin-kuat>

## Install deploy command

`install -m 0755 /opt/dapetotp/app/deploy/deploy.sh /usr/local/bin/dapetotp-deploy`

## Caddy setelah pengujian

Ubah site domain menjadi:

dapetotp.jagoanproject.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3281
}

Validasi dan reload:

caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy

## Catatan keamanan penting

Source backend saat ini memiliki kode startup yang otomatis membuat akun demo
`user@dapetotp.com` dengan password hardcoded jika akun itu belum ada.
Hapus/nonaktifkan blok demo tersebut sebelum production.

Backend baru menggunakan MongoDB. Data Laravel/PostgreSQL lama tidak otomatis berpindah.
