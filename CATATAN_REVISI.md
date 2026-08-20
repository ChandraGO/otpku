# Revisi Scroll Assembly Home v2

File utama yang berubah:

- `frontend/src/pages/Landing.jsx`

Perbaikan:

1. **State final animasi terlihat jelas di desktop**
   - Objek dan card sekarang selesai merakit sekitar 62% progress scroll.
   - Posisi final ditahan sampai akhir sticky section, jadi tidak baru selesai ketika section sudah mulai terlepas/ter-scroll ke atas.
   - Tinggi stage desktop disetel agar transisi cukup panjang tanpa meninggalkan ruang kosong berlebihan.

2. **Animasi scroll assembly aktif di HP/tablet**
   - Hero copy tampil normal terlebih dahulu.
   - Setelah copy, ada stage assembly khusus mobile yang `sticky` dan mengikuti scroll.
   - Scroll turun merakit objek, scroll naik membalik animasi.
   - Ukuran card, badge, posisi objek, dan jarak disesuaikan untuk layar kecil.

3. **Final hold / assemble dwell**
   - Card utama, halo, badge, dan progress bar mencapai final lebih awal dan tetap diam pada state assembled beberapa saat sebelum lanjut ke section berikutnya.

4. **Reduced motion**
   - Jika perangkat meminta reduced motion, layout langsung tampil pada posisi final tanpa animasi berat.

Tidak ada dependency baru. Tetap menggunakan `framer-motion` yang sudah ada di project.

## Deploy

Timpa file sesuai struktur ZIP, lalu:

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "fix scroll assembly final state and mobile"
git push origin master
```
