# Perubahan 20 Agustus 2026

Perbaikan pada paket ini:

- Scrollbar global mengikuti warna tema light/dark, termasuk sidebar admin dan blok kode.
- Input password/secret memakai tombol mata buka/tutup pada halaman login/daftar dan pengaturan admin.
- Switch boolean admin diganti ke komponen Radix Switch yang memiliki posisi thumb stabil dan tidak keluar dari track.
- Endpoint harga publik diberi `Cache-Control: no-store` agar perubahan markup langsung terlihat.
- Landing page selalu memakai harga publik level Member, bukan tier dari cookie admin/user yang sedang login.
- Override harga per layanan yang dikosongkan sekarang benar-benar dihapus sehingga tidak lagi menutupi markup global lama secara diam-diam.
- Halaman Harga per Layanan tetap menampilkan perbandingan `provider -> jual` sebagai pengecekan harga akhir.
- Landing page didesain ulang: hero dua kolom, panel API, blok contoh request, feature cards, runtime flow, live stats, daftar harga, dan FAQ.

## Deploy

Backup project lama, lalu ganti source dengan isi paket ini. Dari folder aplikasi:

```bash
cd /opt/dapetotp/app

docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  up -d --build --force-recreate backend web
```

Cek log:

```bash
docker compose \
  --env-file /opt/dapetotp/production.env \
  -p dapetotp \
  -f compose.yaml \
  logs --tail=100 backend web
```

Setelah deploy, hard refresh browser (`Ctrl+Shift+R`). Untuk mengecek markup, buka Admin -> Harga per Layanan dan lihat teks `provider Rp... -> jual Rp...`.

## Branding dinamis & hapus sisa Emergent
- Menghapus title/meta/script publik yang masih memakai branding Emergent dari `frontend/public/index.html`.
- Judul awal tab sekarang `dapetOTP — Sewa Nomor Virtual & OTP Instan` supaya tidak ada flash "Emergent" saat halaman pertama dibuka.
- Menambahkan `SiteContext`: pengaturan `site_name`, `meta_title`, `meta_description`, `meta_keywords`, `favicon_url`, dan `share_thumbnail_url` diterapkan langsung ke UI/metadata browser.
- `site_name` sekarang mengubah brand navbar, landing/footer, dan judul Admin tanpa edit source.
- `meta_title` sekarang mengubah judul tab browser setelah klik Simpan di Admin → Situs & SEO.
- Endpoint `/api/public/settings` diberi `Cache-Control: no-store` agar perubahan branding segera terbaca.
- Menambahkan favicon default lokal agar tidak bergantung pada branding eksternal.
