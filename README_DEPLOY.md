# dapetOTP - Deploy Production

Struktur repository utama:

- `.github/workflows/deploy.yml`
- `frontend/`
- `backend/`
- `deploy/`
- `compose.yaml`

Stack web menggunakan bind lokal `127.0.0.1:3280` agar sesuai dengan deployment aktif yang sedang dipakai reverse proxy.

## Environment VPS

Simpan environment production di `/opt/dapetotp/production.env` dan beri permission `600`:

```env
MONGO_ROOT_USERNAME=dapetotp
MONGO_ROOT_PASSWORD=<password-mongo-yang-sudah-dipakai-volume>
DB_NAME=dapetotp
JWT_SECRET=<hex-random>
FRONTEND_URL=https://dapetotp.jagoanproject.com
ADMIN_EMAIL=<email-admin>
ADMIN_PASSWORD=<password-admin-kuat>
```

> Jangan mengganti `MONGO_ROOT_PASSWORD` sembarangan setelah volume Mongo sudah terbentuk. Environment `MONGO_INITDB_*` hanya dipakai saat inisialisasi database pertama kali.

## Reverse proxy

Contoh Caddy:

```caddy
dapetotp.jagoanproject.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3280
}
```

Validasi dan reload:

```bash
caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
```

## Auto deploy GitHub

Workflow `.github/workflows/deploy.yml` akan menjalankan deploy setiap push ke branch `main` atau `master`.

Secrets GitHub yang diperlukan:

- `VPS_HOST`
- `VPS_USER`
- `VPS_PORT`
- `VPS_SSH_KEY`
- `VPS_KNOWN_HOSTS`

Detail setup ada di `AUTO_DEPLOY_GITHUB.md`.

## Cache frontend

`index.html` dikirim dengan `no-store/no-cache`, sedangkan asset JS/CSS ber-hash boleh di-cache lama. Saat source frontend berubah, script deploy membangun image web dengan `--no-cache` supaya bundle lama tidak tertinggal.
