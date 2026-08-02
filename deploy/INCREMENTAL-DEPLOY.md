# KodeOTP incremental deployment v13

Pipeline ini tidak menjalankan installer lagi.

## Klasifikasi perubahan

- `resources/views`, `resources/css`, `resources/js`, `vite.config.js`:
  hanya build aset Vite/Tailwind dan blue-green restart ringan.
- `app`, `routes`, `config`, `database`, `bootstrap`:
  tidak build image; source dipasang sebagai release read-only dan container
  baru dinyalakan dari runtime image yang sudah ada.
- `database/migrations`:
  hanya migration yang belum pernah dijalankan.
- dokumentasi atau workflow:
  tidak restart aplikasi.
- `Dockerfile`, `composer.json`, `composer.lock`, `deploy/docker/*`:
  full image build, karena benar-benar mengubah runtime/dependency PHP.

Setiap commit disalin ke `/opt/kodeotp/releases/<sha>`. Container aktif tetap
menggunakan release lama sampai container kandidat sehat dan gateway lokal
lulus pemeriksaan. Ini mencegah `git reset` mengubah source yang sedang aktif.

File `/opt/kodeotp/.deployed_sha` menyimpan commit terakhir yang benar-benar
berhasil. Deploy yang gagal tidak membuat perubahan berikutnya kehilangan diff.
