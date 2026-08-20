# Revisi Scroll Assembly Home V4

Fokus revisi ini adalah memisahkan **hero text** dan **assembly animation** agar tidak saling menimpa.

## Perubahan utama

- Hero awal tampil normal saat refresh; tidak ada entrance animation otomatis.
- Saat user mulai scroll, konten hero bergerak naik secara progresif.
- Konten hero juga semakin blur + redup seperti pola transisi pada referensi MEGA.
- Scene assembly tidak lagi berada di tengah sejak awal.
- Assembly baru muncul dari **bagian bawah viewport** setelah hero mulai naik/blur.
- Badge `API KEY`, `OTP LIVE`, `SALDO`, `200 OK`, dan `MULTI SERVER` tetap mengikuti scroll dan berkumpul ke kartu.
- State final assembly selesai lebih awal lalu ditahan cukup lama sebelum masuk ke section `Contoh Request`.
- Layout desktop dan mobile sama-sama mendapat flow dua fase tersebut.
- Hero scene dibuat sedikit lebih panjang agar fase blur -> assembly -> final punya ruang scroll yang cukup.

## File yang berubah

- `frontend/src/pages/Landing.jsx`

## Alur baru

1. Refresh / posisi awal: hero bersih dan stabil.
2. Mulai scroll: hero naik.
3. Scroll lanjut: hero blur dan meredup.
4. Dari bawah: assembly visual naik masuk ke viewport.
5. Badge berkumpul ke card.
6. Final card ditahan di layar.
7. Baru setelah itu section berikutnya masuk.
