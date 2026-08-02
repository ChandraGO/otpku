# Integrasi dengan VPS yang sudah menjalankan REST API dan payment

Arsip referensi VPS yang diperiksa menggunakan gateway REST API pada `127.0.0.1:3180` dan Caddy utama sebagai pemilik port publik 80/443. Stack KodeOTP sengaja memakai project Compose, network, volume, container, database, Redis, dan gateway berbeda.

| Layanan | Bind host | Pemilik TLS/publik |
|---|---:|---|
| REST API lama | `127.0.0.1:3180` | Caddy utama VPS |
| KodeOTP | `127.0.0.1:3280` | Caddy utama VPS |
| Payment lama | pertahankan port lama | Caddy utama VPS |

Jangan menjalankan Caddy KodeOTP pada host network dan jangan publish `80:80` atau `443:443`. Compose bawaan hanya mem-publish `127.0.0.1:3280:80`.

Tambahkan blok berikut ke konfigurasi **Caddy utama**, tanpa menghapus site REST API atau payment yang sudah ada:

```caddy
otp.domainanda.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3280
}
```

Contoh bentuk akhirnya:

```caddy
api.domainanda.com {
    reverse_proxy 127.0.0.1:3180
}

payment.domainanda.com {
    reverse_proxy 127.0.0.1:PORT_PAYMENT_LAMA
}

otp.domainanda.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3280
}
```

Validasi sebelum reload:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
sudo bash /opt/kodeotp/app/deploy/check-routing.sh
```

Bila `3280` ternyata sudah digunakan, instal dengan `KODEOTP_GATEWAY_PORT=3281` dan gunakan port yang sama pada blok Caddy utama.

Semua perintah update KodeOTP memakai Compose project `kodeotp`. Jangan menjalankan `docker compose down` tanpa `-p kodeotp` dan file `/opt/kodeotp/docker-compose.yml`, agar service lain tidak tersentuh.
