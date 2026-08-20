# Revisi Scroll Assembly Home V5

Fokus revisi ini adalah masalah yang terlihat pada screenshot terakhir: kartu final terpotong oleh batas scene dan terdapat area kosong terlalu panjang sebelum section "Contoh Request".

## Perbaikan

- Wrapper assembly sekarang memakai **seluruh tinggi sticky viewport**, bukan hanya 66–72% bagian bawah layar.
- Ukuran visual desktop diperkecil dari 500px menjadi 390px agar muat pada viewport desktop pendek (mis. tinggi sekitar 560px dengan navbar tetap terlihat).
- Ukuran visual mobile juga dipadatkan agar seluruh kartu tetap terlihat tanpa terpotong.
- Card, badge, halo, padding, dan typography final dipadatkan secara proporsional.
- Garis indikator `SCATTER / ASSEMBLED` dihapus karena tidak ada pada referensi MEGA dan ikut membuat komposisi terlihat seperti terpotong.
- Scene scroll dipendekkan dari sekitar 248vh menjadi 180vh desktop / 185–190svh tablet-mobile sehingga tidak ada scroll kosong panjang.
- Timeline tetap: hero normal -> scroll -> hero naik + blur -> assembly dari bawah -> final tersusun.
- State final selesai sekitar 58% progress dan **ditahan sampai scene selesai**. Tidak dibuat fade kosong di ujung.
- Final assembly ditempatkan sedikit di bawah tengah viewport sehingga lebih mirip komposisi MEGA dan seluruh kartu terlihat.
- Saat scene sticky selesai, section `Contoh Request` langsung masuk dari bawah.
- Animasi tetap scroll-linked; refresh tidak memainkan entrance animation.

## File yang berubah

- `frontend/src/pages/Landing.jsx`

## Deploy

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "fix hero assembly clipping and empty space"
git push origin master
```
