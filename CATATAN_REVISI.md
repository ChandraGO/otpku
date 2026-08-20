# Revisi Home — Scroll-scrubbed assembly animation

File yang berubah:

- `frontend/src/pages/Landing.jsx`

## Efek yang ditambahkan

- Hero desktop menjadi section panjang dengan area `sticky`, sehingga visual tetap terlihat saat user scroll.
- Progress animasi benar-benar mengikuti scrollbar dan otomatis mundur saat scroll ke atas.
- Panel API kanan mulai dalam posisi miring/offset lalu tersusun ke posisi final.
- Lima elemen kecil (`API KEY`, `OTP LIVE`, `SALDO`, `200 OK`, `MULTI SERVER`) mulai tersebar lalu berkumpul ke area visual.
- Ring/halo ikut berotasi mengikuti scroll.
- Copy hero bergerak sangat halus untuk memberi depth.
- Ada indikator SCATTER → ASSEMBLE di bawah visual desktop.
- Animasi tombol/chip `soft-float` dari revisi sebelumnya tetap digunakan.
- Efek scrub hanya aktif mulai lebar 1024px agar mobile tetap ringan dan rapi.
- `prefers-reduced-motion` dihormati untuk aksesibilitas.

## Dependency

Tidak perlu menambah package baru. Project sudah memiliki `framer-motion`, dan revisi ini memakai `useScroll` + `useTransform` dari package tersebut.

## Pasang

Timpa file sesuai path repo, lalu:

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "add scroll scrub assembly hero"
git push origin master
```
