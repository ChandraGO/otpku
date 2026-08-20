# Optimasi auto-deploy dapetOTP

Dari log 9m05s, bottleneck utama:

- `yarn install`: sekitar 262.5 detik
- `yarn build`: sekitar 231.7 detik
- langkah GitHub/SSH lain hanya belasan detik

Perubahan paket ini:

1. Menghapus `--no-cache` pada build frontend. Ini yang sebelumnya memaksa dependency dipasang ulang setiap deploy.
2. Dependency Docker menjadi cacheable selama `package.json` dan `yarn.lock` tidak berubah.
3. Menambahkan BuildKit cache untuk cache Yarn dan `node_modules/.cache` (webpack/CRACO).
4. Menonaktifkan production source map dan ESLint plugin di tahap Docker build untuk mempercepat build production.
5. Backend build di-skip bila backend tidak berubah (mekanisme fingerprint tetap dipakai).
6. Frontend build di-skip bila frontend tidak berubah.
7. Container tidak disentuh bila tidak ada perubahan aplikasi.
8. Workflow tidak lagi menjalankan `apt-get update` bila `sshpass` dan `rsync` sudah tersedia. Pada log saat ini keduanya memang sudah tersedia.
9. Tes SSH dan pengecekan dependency VPS digabung menjadi satu koneksi.
10. Workflow hanya otomatis berjalan bila file aplikasi/deploy relevan berubah.

Catatan: deploy pertama setelah perubahan Dockerfile bisa masih cukup lama karena cache harus dibuat. Deploy frontend berikutnya dengan dependency yang sama yang akan mendapat keuntungan terbesar.
