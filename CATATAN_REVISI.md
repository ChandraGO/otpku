# Revisi Scroll Assembly Home V11

Fokus revisi ini hanya memperbaiki state final animasi scroll tanpa mengubah tampilan hero awal yang sudah pas.

## Penyebab kartu final terpotong

Pada V10, elemen sticky memakai `overflow-hidden`. Ketika sticky mendekati akhir container, sticky mulai ikut bergerak ke atas. Karena clipping ikut bergerak, bagian bawah API card dipotong walaupun posisi card sudah diturunkan.

## Perubahan V11

- `sticky ... overflow-hidden` diubah menjadi `overflow-visible`, sehingga card/badge tidak dipotong saat sticky release.
- Seluruh assembly final diturunkan sedikit lagi.
- Tinggi stage internal diperbesar agar area raster/komposisi cukup untuk card + badge.
- Assembly mencapai state final lebih cepat (sekitar 60% progress).
- Tinggi scene dipangkas menjadi sekitar 122–126vh agar jarak ke `Contoh Request` lebih pendek.
- Tampilan awal hero tetap sama seperti V10/V9.

File yang berubah:

- `frontend/src/pages/Landing.jsx`
