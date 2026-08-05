# Auto deploy GitHub → VPS

Workflow `.github/workflows/deploy.yml` berjalan otomatis setiap ada push ke branch `master` atau `main`. Polanya sama dengan project REST API: GitHub Actions masuk ke VPS melalui SSH, mengambil `deploy/kodeotp-update.sh` dari commit terbaru, lalu menjalankan deployment incremental di `/opt/kodeotp`.

## Secret GitHub

Wajib:

- `VPS_SSH_KEY`: private key untuk koneksi GitHub Actions ke VPS.

Opsional karena sudah memiliki default dari VPS contoh:

- `VPS_HOST` — default `46.247.108.54`
- `VPS_USER` — default `root`
- `VPS_PORT` — default `22`

Tambahkan secret melalui **Repository → Settings → Secrets and variables → Actions → New repository secret**.

Public key pasangan `VPS_SSH_KEY` harus ada pada `/root/.ssh/authorized_keys` di VPS. Jangan memasukkan private key ke source code atau `.env`.

## Akses VPS ke repository GitHub

GitHub Actions hanya membuka sesi SSH ke VPS. Perintah deployment kemudian menjalankan `git fetch` dari VPS, sehingga `/opt/kodeotp/app` juga harus dapat membaca repository GitHub.

- Repository publik dapat memakai remote HTTPS.
- Repository private sebaiknya memakai GitHub Deploy Key read-only yang disimpan di VPS dan public key-nya ditambahkan pada **Repository → Settings → Deploy keys**.

## Pemeriksaan satu kali di VPS

```bash
cd /opt/kodeotp/app
git remote -v
sudo APP_DIR=/opt/kodeotp/app STACK_DIR=/opt/kodeotp BRANCH=master \
  bash deploy/check-github-autodeploy.sh
```

Bila branch utama repository adalah `main`, ganti `BRANCH=main`.

## Cara memakai

Setelah secret dan akses Git sudah benar, cukup commit dan push:

```bash
git add .
git commit -m "Update KodeOTP"
git push origin master
```

Tab **Actions** akan menampilkan workflow **Deploy KodeOTP ke VPS**. Deploy memakai lock agar dua push yang berdekatan tidak menjalankan update secara bersamaan, menjalankan migration bila diperlukan, membangun asset hanya ketika CSS/JS berubah, dan mempertahankan `.env` yang berada di VPS.

## Data yang tidak ditimpa

Deployment tidak mengirim atau mengganti:

- `/opt/kodeotp/app.env`
- `/opt/kodeotp/.env`
- database PostgreSQL
- data Redis
- volume backup
- API key Pakasir dan SMS Virtuals yang tersimpan di server/database

## Jika workflow gagal

Periksa log pada GitHub Actions. Di VPS, jalankan:

```bash
cd /opt/kodeotp/app
git fetch origin master
tail -n 200 /var/log/syslog 2>/dev/null || true
docker compose --env-file /opt/kodeotp/.env -p kodeotp \
  -f /opt/kodeotp/docker-compose.yml ps
```

Penyebab paling umum adalah `VPS_SSH_KEY` belum benar, public key belum masuk `authorized_keys`, atau VPS belum mempunyai akses `git fetch` ke repository private.
