# dapetOTP

Aplikasi web nomor virtual/OTP dengan backend FastAPI, MongoDB, frontend React, Docker Compose, dan auto-deploy GitHub Actions.

Integrasi Kode OTp : https://sms-virtual.net/
Integrasi Payment Polling : https://pay.digikita.id/

---

# 1. Jangan upload credential ke GitHub

Repository ini tidak membutuhkan API key provider, password MongoDB, JWT secret, atau password admin disimpan di source code.

File yang **tidak boleh** di-commit:

- `.env`
- `.env.*` selain `.env.example`
- `production.env`
- private key SSH (`*.key`, `*.pem`)
- backup JSON dari menu Admin
- dump MongoDB

`.gitignore` sudah disiapkan untuk mencegah file-file umum tersebut ikut ter-commit.

> Penting: API key SMS Virtual, PayKita, SMTP password, Telegram bot token, dan webhook secret yang diisi dari halaman Admin disimpan di **MongoDB production**, bukan di repository. Karena itu jangan pernah upload database/dump/backup production ke repository publik.

Sebelum push, cek cepat:

```bash
git status --short

git grep -nEi 'api[_-]?key|secret|token|password|private key' -- ':!frontend/yarn.lock'
```

Hasil grep dapat berisi **nama field** seperti `api_key` atau `password`; yang berbahaya adalah bila ada **nilai credential asli** di sampingnya.

---

# 2. Kebutuhan server

Rekomendasi:

- Ubuntu 22.04/24.04
- 2 GB RAM atau lebih
- Docker + Docker Compose v2
- domain/subdomain, contoh `otp.example.com`
- port 22, 80, dan 443 terbuka

Arsitektur production:

```text
Internet
   |
Nginx host (HTTPS :443)
   |
127.0.0.1:3280
   |
Docker web (Nginx frontend)
   |
Docker backend FastAPI :8000
   |
Docker MongoDB
```

Port aplikasi Docker hanya dibind ke `127.0.0.1:3280`, sehingga tidak terbuka langsung ke internet.

---

# 3. Upload source ke GitHub

## 3.1 Buat repository kosong

Di GitHub buat repository baru, misalnya:

```text
dapetotp
```

Sebaiknya pilih **Private** sampai deployment dan audit selesai.

## 3.2 Dari komputer lokal

Masuk ke folder source:

```bash
cd dapetotp
```

Inisialisasi Git:

```bash
git init
git add .
git status
git commit -m "Initial dapetOTP production"
git branch -M main
```

Hubungkan repository GitHub:

```bash
git remote add origin git@github.com:USERNAME/dapetotp.git
git push -u origin main
```

Ganti `USERNAME` dengan username/organization GitHub Anda.

---

# 4. Arahkan domain ke VPS

Di DNS provider/domain manager buat record:

```text
Type: A
Name: otp
Value: IP_VPS_ANDA
TTL: Auto / 300
```

Contoh hasil:

```text
otp.example.com -> 203.0.113.10
```

Tunggu propagasi DNS, lalu cek:

```bash
ping otp.example.com
```

atau:

```bash
nslookup otp.example.com
```

Pastikan IP yang muncul adalah IP VPS Anda.

---

# 5. Persiapkan VPS

SSH ke VPS:

```bash
ssh root@IP_VPS
```

Update package:

```bash
apt update && apt upgrade -y
```

Install kebutuhan dasar:

```bash
apt install -y docker.io docker-compose-v2 git rsync nginx certbot python3-certbot-nginx openssl ufw
systemctl enable --now docker
systemctl enable --now nginx
```

Cek Docker:

```bash
docker --version
docker compose version
```

## 5.1 Buat user deploy (disarankan)

```bash
adduser deploy
usermod -aG docker deploy
mkdir -p /opt/dapetotp/app /opt/dapetotp/.deploy-state
chown -R deploy:deploy /opt/dapetotp
```

Setelah itu login sebagai user deploy:

```bash
su - deploy
```

Cek akses Docker:

```bash
docker ps
```

Jika `permission denied`, logout lalu login lagi agar group `docker` aktif.

---

# 6. Buat environment production di VPS

Generate nilai acak terlebih dahulu:

```bash
openssl rand -base64 36
openssl rand -hex 32
openssl rand -base64 32
```

Buat file:

```bash
sudo nano /opt/dapetotp/production.env
```

Isi contoh:

```env
MONGO_ROOT_USERNAME=dapetotp
MONGO_ROOT_PASSWORD=GANTI_PASSWORD_MONGO_ACAK
DB_NAME=dapetotp
JWT_SECRET=GANTI_RANDOM_HEX_MINIMAL_64_CHAR
FRONTEND_URL=https://otp.example.com
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=GANTI_PASSWORD_ADMIN_KUAT
```

Simpan lalu kunci permission:

```bash
sudo chown deploy:deploy /opt/dapetotp/production.env
sudo chmod 600 /opt/dapetotp/production.env
```

**Jangan** taruh API key SMS Virtual di file repository. API key provider nanti dimasukkan dari halaman Admin setelah situs hidup.

> Jika MongoDB sudah pernah dibuat dan volume lama masih dipakai, jangan mengganti `MONGO_ROOT_PASSWORD` sembarangan. `MONGO_INITDB_ROOT_*` hanya berlaku ketika database pertama kali diinisialisasi.

---

# 7. Siapkan SSH key khusus GitHub Actions

Lakukan dari komputer lokal/admin machine, bukan dari repository.

Buat key khusus deploy:

```bash
ssh-keygen -t ed25519 -f dapetotp_deploy -C "github-actions-dapetotp"
```

Akan terbentuk:

```text
dapetotp_deploy       <- PRIVATE KEY, rahasiakan
dapetotp_deploy.pub   <- PUBLIC KEY
```

Copy public key ke VPS user `deploy`:

```bash
ssh-copy-id -i dapetotp_deploy.pub deploy@IP_VPS
```

Tes:

```bash
ssh -i dapetotp_deploy deploy@IP_VPS
```

Jika berhasil tanpa password, key siap.

## 7.1 Ambil known_hosts

Dari komputer lokal:

```bash
ssh-keyscan -p 22 -H IP_VPS
```

Untuk keamanan, bandingkan fingerprint host key dengan VPS:

```bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Pastikan fingerprint cocok sebelum menaruh hasil `ssh-keyscan` ke GitHub Secret.

---

# 8. Isi GitHub Actions Secrets

Di GitHub buka:

```text
Repository
-> Settings
-> Secrets and variables
-> Actions
-> New repository secret
```

Tambahkan:

```text
VPS_HOST        = IP atau hostname VPS
VPS_USER        = deploy
VPS_PORT        = 22
VPS_SSH_KEY     = seluruh isi private key dapetotp_deploy
VPS_KNOWN_HOSTS = hasil ssh-keyscan yang sudah diverifikasi
```

Jangan menambahkan `production.env` ke GitHub Secrets karena file itu sudah berada langsung di VPS.

Workflow `.github/workflows/deploy.yml` akan:

1. checkout source,
2. rsync source ke `/opt/dapetotp/app`,
3. tidak mengirim `.env`, `production.env`, cache, atau private key,
4. build hanya bagian yang berubah,
5. menjalankan container,
6. health-check frontend dan API.

---

# 9. Jalankan deploy pertama

Ada dua cara.

## Cara A - Push commit

```bash
git add .
git commit -m "Deploy production"
git push
```

## Cara B - Manual dari GitHub

```text
GitHub -> Actions -> Deploy dapetOTP ke VPS -> Run workflow
```

Setelah workflow hijau, masuk ke VPS dan cek:

```bash
cd /opt/dapetotp/app

docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  ps
```

Cek frontend lokal:

```bash
curl -I http://127.0.0.1:3280/
```

Cek API:

```bash
curl http://127.0.0.1:3280/api/public/settings
```

Jika keduanya merespons, container sudah hidup.

---

# 10. Pasang reverse proxy domain dengan Nginx

Sebagai root/sudo:

```bash
sudo nano /etc/nginx/sites-available/dapetotp
```

Isi:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name otp.example.com;

    client_max_body_size 10m;

    location / {
        proxy_pass http://127.0.0.1:3280;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
```

Aktifkan:

```bash
sudo ln -s /etc/nginx/sites-available/dapetotp /etc/nginx/sites-enabled/dapetotp
sudo nginx -t
sudo systemctl reload nginx
```

Tes HTTP:

```bash
curl -I http://otp.example.com
```

---

# 11. Aktifkan HTTPS/SSL

Jalankan:

```bash
sudo certbot --nginx -d otp.example.com
```

Ikuti proses Certbot lalu cek:

```bash
curl -I https://otp.example.com
```

Cek timer renewal:

```bash
systemctl status certbot.timer
```

---

# 12. Firewall

Jika memakai UFW:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

Port `3280` tidak perlu dibuka karena hanya bind ke `127.0.0.1`.

---

# 13. Login admin pertama kali

Buka:

```text
https://otp.example.com
```

Login menggunakan:

```text
Email    = ADMIN_EMAIL dari production.env
Password = ADMIN_PASSWORD dari production.env
```

Backend akan membuat akun admin saat startup pertama. Jika password di `production.env` berubah, startup berikutnya akan menyamakan password admin dengan nilai environment tersebut.

Tidak ada lagi akun demo/password demo hardcoded di source.

---

# 14. Konfigurasi provider SMS Virtual

Masuk:

```text
Admin -> Pengaturan -> Provider Nomor
```

Isi minimal:

- API Key provider
- `enabled = ON`
- timeout provider sesuai kebutuhan
- auto search operator/server bila digunakan

API key disimpan di MongoDB dan pada response halaman Admin hanya ditampilkan dalam bentuk masked.

---

# 15. Setting auto-cancel yang direkomendasikan

Masuk:

```text
Admin -> Pengaturan -> Pesanan
```

Default versi ini:

```text
Jeda minimum sebelum cancel provider        = 120 detik
Buffer cancel minimum sebelum provider habis = 180 detik
Buffer relatif                               = 0.20
Safety margin                                = 45 detik
Auto refund saat provider expired            = ON
```

Contoh jika provider memberi masa aktif 15 menit:

```text
Provider expired : menit 15:00
Auto-cancel mulai: sekitar menit 12:00
Sisa buffer      : sekitar 3 menit
```

Jika request cancel pertama gagal karena timeout/error, worker akan mencoba kembali tiap sekitar 3 detik selama provider belum habis.

### Arti status riwayat

- `cancelled`: provider berhasil/terkonfirmasi dibatalkan dan saldo user dikembalikan.
- `expired`: provider sudah habis sebelum cancel dapat dikonfirmasi. Ini harus dianggap kejadian yang perlu diperiksa di log/provider.
- `success`: OTP diterima/selesai.

---

# 16. Monitor auto-cancel

Lihat log backend:

```bash
cd /opt/dapetotp/app

docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  logs -f --tail=200 backend
```

Cari event cancel/provider loss:

```bash
docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  logs --no-color backend | grep -Ei 'auto cancel|provider loss|expired sebelum cancel'
```

Contoh log sehat:

```text
auto cancel provider berhasil untuk order ...
```

Contoh yang harus diaudit:

```text
provider loss: order ... expired sebelum cancel terkonfirmasi
```

---

# 17. Troubleshooting

## GitHub Actions gagal SSH

Cek:

- `VPS_HOST`
- `VPS_USER`
- `VPS_PORT`
- isi private key di `VPS_SSH_KEY`
- public key ada di `/home/deploy/.ssh/authorized_keys`
- `VPS_KNOWN_HOSTS` sesuai host key VPS

Tes manual:

```bash
ssh -i dapetotp_deploy -p 22 deploy@IP_VPS
```

## Docker permission denied

```bash
sudo usermod -aG docker deploy
```

Logout/login lagi.

## Situs 502 Bad Gateway

Cek container:

```bash
cd /opt/dapetotp/app

docker compose --env-file /opt/dapetotp/production.env -p dapetotp -f compose.yaml ps
```

Cek backend:

```bash
docker compose --env-file /opt/dapetotp/production.env -p dapetotp -f compose.yaml logs --tail=200 backend
```

## Frontend masih versi lama

Deploy script memakai fingerprint source dan rebuild image frontend bila source frontend berubah. Hard refresh browser setelah deploy:

```text
Ctrl + Shift + R
```

`index.html` dikirim `no-store`, sedangkan asset ber-hash boleh di-cache lama.

## Auto-cancel masih terlambat

Naikkan buffer di Admin -> Pengaturan -> Pesanan, misalnya:

```text
provider_cancel_buffer_seconds = 240
provider_cancel_buffer_ratio   = 0.25
```

Jangan hanya memperkecil `safety_margin`; tujuan utama adalah **mulai cancel lebih awal**, bukan mencoba sedekat mungkin dengan expired provider.

---

# 18. Update aplikasi setelah production hidup

Setiap perubahan source:

```bash
git add .
git commit -m "Update fitur"
git push
```

GitHub Actions akan deploy otomatis.

Untuk cek hasil deploy di VPS:

```bash
cd /opt/dapetotp/app
bash deploy/deploy.sh
```

Perintah manual tersebut menggunakan default `/opt/dapetotp/production.env`.

---

# 19. Backup

Backup database production mengandung data sensitif. Jika Anda memakai menu Admin -> Backup atau `mongodump`, simpan di storage privat dan jangan commit ke GitHub.

Khusus backup Admin, koleksi `settings` dapat berisi:

- API key provider
- webhook secret
- SMTP password
- Telegram bot token

Jadi source repository boleh dibagikan setelah audit, tetapi **backup database tidak boleh dibagikan**.

---

# 20. Checklist sebelum repository dibuat Public

- [ ] Tidak ada `.env` / `production.env`
- [ ] Tidak ada private SSH key
- [ ] Tidak ada backup JSON / Mongo dump
- [ ] Tidak ada API key provider asli
- [ ] Tidak ada password SMTP asli
- [ ] Tidak ada Telegram bot token asli
- [ ] Tidak ada webhook secret asli
- [ ] `backend/__pycache__` tidak ada
- [ ] Hanya workflow deploy yang memang dipakai
- [ ] Domain/IP pribadi yang tidak ingin dipublikasikan sudah diganti
- [ ] Git history lama juga sudah diperiksa bila repository sebelumnya pernah berisi secret

> Catatan: `.gitignore` hanya mencegah secret baru ikut ter-commit. Jika secret **pernah** masuk ke Git history, hapus dari history dan **rotate credential** tersebut. Menghapus file pada commit terbaru saja tidak cukup.
