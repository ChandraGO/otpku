# Auto Deploy GitHub -> VPS untuk dapetOTP

Patch ini hanya berisi file yang berhubungan dengan auto deploy:

- `.github/workflows/deploy.yml`
- `deploy/deploy.sh`
- `AUTO_DEPLOY_GITHUB.md`

Tidak ada source frontend/backend lain di ZIP patch ini.

## Cara kerja

Setiap ada `git push` ke branch `main` atau `master`:

1. GitHub Actions checkout commit terbaru.
2. Source disinkronkan dengan `rsync` ke `/opt/dapetotp/app/`.
3. File secret/env tidak ikut dikirim.
4. VPS menjalankan `deploy/deploy.sh`.
5. Script membandingkan fingerprint backend dan frontend.
6. Hanya service yang berubah yang dibuild ulang.
7. Docker Compose memastikan stack tetap hidup.
8. Frontend dan endpoint backend dicek sebelum deploy dinyatakan berhasil.

MongoDB tetap menggunakan Docker volume `mongo_data`, sedangkan environment production tetap berada di `/opt/dapetotp/production.env`.

## 1. Persiapan VPS satu kali

Pastikan Docker, Docker Compose plugin, curl, Python 3, rsync, dan flock tersedia.

Ubuntu/Debian:

```bash
apt update
apt install -y rsync curl python3 util-linux

docker --version
docker compose version
rsync --version
```

Pastikan folder aplikasi dan env production sudah ada:

```bash
mkdir -p /opt/dapetotp/app
chmod 755 /opt/dapetotp/app

test -f /opt/dapetotp/production.env && echo "production.env OK"
chmod 600 /opt/dapetotp/production.env
```

## 2. Buat SSH key khusus GitHub Actions

Buat key di komputer yang aman:

```bash
ssh-keygen -t ed25519 -C "github-actions-dapetotp" -f dapetotp_github_actions -N ""
```

Hasilnya:

- `dapetotp_github_actions` = PRIVATE KEY, masukkan ke GitHub Secret `VPS_SSH_KEY`.
- `dapetotp_github_actions.pub` = PUBLIC KEY, pasang ke VPS.

Jika workflow akan login sebagai `root`, pasang public key ke VPS:

```bash
mkdir -p /root/.ssh
chmod 700 /root/.ssh
cat >> /root/.ssh/authorized_keys
```

Paste satu baris isi file `dapetotp_github_actions.pub`, tekan Enter, lalu `Ctrl+D`.

Kemudian:

```bash
chmod 600 /root/.ssh/authorized_keys
```

## 3. Tambahkan GitHub Actions Secrets

Di repository GitHub buka:

`Settings -> Secrets and variables -> Actions -> New repository secret`

Buat secret berikut:

- `VPS_HOST` = IP/domain VPS, contoh `123.123.123.123`
- `VPS_USER` = user SSH, untuk setup kamu biasanya `root`
- `VPS_PORT` = port SSH, biasanya `22`
- `VPS_SSH_KEY` = seluruh isi PRIVATE KEY `dapetotp_github_actions`
- `VPS_KNOWN_HOSTS` = SSH host key VPS

Untuk membuat nilai `VPS_KNOWN_HOSTS`, dari komputer yang dipercaya jalankan:

```bash
ssh-keyscan -p 22 IP_VPS
```

Jika SSH memakai port lain, ganti `22`. Sebelum menyimpan, cocokkan fingerprint host dengan fingerprint SSH server VPS agar tidak mempercayai host yang salah.

## 4. Masukkan patch ke repository

Ekstrak ZIP patch di root repository dapetOTP sehingga hasilnya seperti ini:

```text
.github/
  workflows/
    deploy.yml
deploy/
  deploy.sh
AUTO_DEPLOY_GITHUB.md
```

File `deploy/deploy.sh` menggantikan file deploy lama jika sudah ada.

Commit dan push:

```bash
git add .github/workflows/deploy.yml deploy/deploy.sh AUTO_DEPLOY_GITHUB.md
git commit -m "Add automatic VPS deployment"
git push
```

Setelah push, buka tab `Actions` di GitHub. Workflow `Deploy dapetOTP ke VPS` akan berjalan otomatis.

## 5. Push berikutnya

Setelah setup pertama selesai, cukup lakukan seperti biasa:

```bash
git add .
git commit -m "Update dapetOTP"
git push
```

Tidak perlu lagi SSH ke VPS untuk rebuild manual. Workflow akan menyinkronkan source dan menjalankan deploy.

## File yang sengaja tidak disinkronkan

Workflow tidak mengirim/menghapus file berikut dari VPS:

- `.git/`
- `.github/`
- `.env`
- `.env.*`
- `production.env`
- `node_modules/`
- `__pycache__/`
- `*.pyc`

`/opt/dapetotp/production.env` berada di luar `/opt/dapetotp/app`, sehingga credential production tidak tersentuh saat deploy.

## Test manual

Workflow juga bisa dijalankan tanpa commit baru melalui:

`GitHub -> Actions -> Deploy dapetOTP ke VPS -> Run workflow`

## Jika workflow gagal

Lihat log pada tab Actions. Untuk diagnosis langsung di VPS:

```bash
cd /opt/dapetotp/app

docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  ps
```

Logs:

```bash
cd /opt/dapetotp/app

docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  logs --tail=150 backend web
```
