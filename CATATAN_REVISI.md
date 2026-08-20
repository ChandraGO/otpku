# Revisi Scroll Assembly Home V3

File yang berubah:

- `frontend/src/pages/Landing.jsx`

## Perubahan utama

1. Hero tidak lagi memainkan animasi `rise` saat halaman baru direfresh.
   - Copy hero langsung tampil stabil.
   - Gerakan assembly baru dimulai setelah user benar-benar mulai scroll.

2. Alur animasi diubah menjadi **top-to-bottom assembly**.
   - Badge `API KEY`, `OTP LIVE`, `SALDO`, `200 OK`, dan `MULTI SERVER` mulai dari posisi di atas/tersebar.
   - Saat scroll turun, badge bergerak turun menuju kartu final.
   - Kartu API muncul bertahap dan menjadi titik kumpul akhir.
   - Scroll ke atas membalik progress secara otomatis.

3. State final selesai sekitar 58% progress dan kemudian ditahan sampai hero selesai.
   - Hasil final tidak lagi baru selesai saat section keburu lepas.
   - User memiliki area scroll yang cukup panjang untuk melihat rakitan final.

4. Desktop dan mobile memakai konsep scroll-driven yang sama.
   - Mobile tidak dimatikan.
   - Ukuran dan jarak badge disesuaikan agar tetap masuk layar HP.

5. Section setelah hero tetap memakai reveal ketika masuk viewport.

## Deploy

Timpa file sesuai struktur repo, kemudian:

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "revise hero scroll assembly v3"
git push origin master
```
