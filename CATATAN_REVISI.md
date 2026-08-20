# Revisi Scroll Assembly Home V8

Fokus V8 hanya memperbaiki fase scroll/final tanpa mengubah komposisi awal V7 yang sudah sesuai.

## Perubahan

- Posisi hero awal tetap rata tengah seperti V7.
- Badge awal tetap tersebar di kiri, kanan, atas, dan bawah.
- Saat scroll berjalan, **seluruh media assembly (kartu + semua badge) ikut turun bertahap**.
- Posisi final desktop diturunkan sekitar 78px; mobile sekitar 48px sehingga rakitan final lebih pas berada di tengah-bawah viewport.
- Tinggi scroll scene dipangkas dari sekitar 154–158vh menjadi sekitar 142–146vh.
- Hold setelah rakitan selesai menjadi lebih singkat, sehingga section **Contoh Request** masuk lebih cepat.
- Mengurangi ruang kosong besar di bawah final animation.
- Tidak mengubah dependency dan tidak menambah library baru.

## File

`frontend/src/pages/Landing.jsx`

## Deploy

```bash
git add frontend/src/pages/Landing.jsx
git commit -m "lower final assembly and reduce hero scroll gap"
git push origin master
```
