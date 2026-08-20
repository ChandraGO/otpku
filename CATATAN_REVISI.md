# Revisi Scroll Assembly Home V7

Fokus revisi ini adalah menyamakan flow hero dengan referensi MEGA yang diberikan.

## Perubahan

- Hero utama sekarang rata tengah pada desktop dan mobile.
- Badge API KEY, OTP LIVE, SALDO, 200 OK, dan MULTI SERVER tersebar di kiri/kanan/atas/bawah mengelilingi hero.
- Badge tidak memainkan entrance animation saat refresh.
- Saat user scroll, hero naik + blur + redup dan badge bergerak langsung menuju pusat.
- Kartu API muncul di pusat lalu badge menyatu mengelilingi kartu final.
- Final assembly diposisikan di tengah viewport, bukan terdorong ke atas.
- Menghapus global `assemblyY` yang sebelumnya menyebabkan kartu final naik, tertutup navbar, atau terpotong.
- Sticky scene sekarang `top-0`; safe-area navbar ditangani di dalam konten sehingga tidak ada pita kosong tambahan di atas hero.
- Tinggi scene dipendekkan menjadi sekitar 154–158vh. State final hanya ditahan sebentar sebelum section `CONTOH REQUEST`, sehingga ruang kosong bawah berkurang drastis.
- Halo/glow dipusatkan dengan wrapper flex agar transform Framer Motion tidak bentrok dengan transform Tailwind.
- Mobile tetap memakai scroll-driven assembly yang sama dengan ukuran/posisi yang disesuaikan.

## File

- `frontend/src/pages/Landing.jsx`

## Deploy

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "center hero and refine scroll assembly v7"
git push origin master
```
