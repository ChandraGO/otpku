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


## Maintenance otomatis setelah push

Mulai versi deployer `2026.08.09-auto-maintenance-v4`, deploy production tidak lagi membutuhkan command maintenance Laravel manual di VPS untuk perubahan normal. Saat commit aplikasi masuk ke `main`/`master`, deployer akan:

- membuat release dari commit terbaru dan mengaktifkannya dengan pola blue/green;
- menjalankan `php artisan migrate --force` otomatis untuk setiap perubahan aplikasi (migration yang sudah pernah jalan otomatis dilewati Laravel);
- membersihkan cache Laravel ketika container web baru mulai, lalu membangun ulang config/route/view cache;
- me-recreate worker dan scheduler ketika kode backend yang relevan berubah;
- membangun Vite hanya bila CSS/JS/dependency frontend berubah;
- melakukan health check sebelum trafik dialihkan ke release baru.

Karena itu, sesudah push GitHub normal Anda tidak perlu lagi SSH ke VPS hanya untuk menjalankan `migrate --force`, `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, atau restart web container. Jika migration atau health check gagal, workflow akan gagal dan trafik lama tetap dipertahankan.

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
