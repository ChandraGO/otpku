# Revisi dapetOTP — 20 Agustus 2026

Paket ini hanya berisi file yang terkait dengan revisi UI/fitur kali ini dan tetap memakai struktur folder repo agar mudah ditimpa.

## Isi revisi

- Logo navbar memakai `favicon_url` dari Admin → Situs & SEO, dengan fallback huruf brand.
- Pengumuman/Changelog admin sekarang bisa **buat + edit + aktif/nonaktif + hapus**.
- Editor changelog bergaya Telegram: Bold, Italic, Underline, Coret, Quote, Spoiler, Bullet, Nomor, Code, dan Link.
- Kolom isi changelog diperbesar dan bisa di-resize.
- Gambar changelog opsional: URL, pilih file, paste Ctrl+V, drag & drop, maks. 4 MB, plus caption dan preview.
- Pengumuman di dashboard merender format rich text + gambar dengan aman tanpa `dangerouslySetInnerHTML`.
- Deskripsi SEO dan catatan admin memakai textarea yang lebih tinggi.
- Landing page: animasi masuk saat scroll, CTA/chip mengambang halus, serta style card dibuat outline/konsisten.
- Teks user-facing tidak lagi menyebut provider/gateway/QRIS sebagai identitas layanan pihak ketiga.
- Detail integrasi internal pada API pesanan disembunyikan dari response pelanggan.
- Logo layanan diproxy lewat domain aplikasi agar URL upstream tidak tampil langsung di browser untuk data baru.
- Pesanan selesai dapat diberi rating 1–5 bintang satu kali.
- Bonus rating: 1★ Rp100, 2★ Rp200, 3★ Rp300, 4★ Rp400, 5★ Rp500; saldo dikreditkan oleh backend dan dicatat sebagai transaksi.
- Nginx menerima request hingga 8 MB agar gambar changelog 4 MB dalam base64 dapat terkirim.

## File yang ditimpa

- `backend/server.py`
- `deploy/nginx.conf`
- `frontend/src/index.css`
- `frontend/src/components/AnnouncementContent.jsx` (baru)
- `frontend/src/components/AdminAnnouncements.jsx`
- `frontend/src/components/AdminSettings.jsx`
- `frontend/src/components/Navbar.jsx`
- `frontend/src/components/OrderCard.jsx`
- `frontend/src/components/Overview.jsx`
- `frontend/src/components/Payment.jsx`
- `frontend/src/components/ServiceCatalog.jsx`
- `frontend/src/pages/Landing.jsx`
- `frontend/src/pages/Faq.jsx`
- `frontend/src/pages/Docs.jsx`

## Deploy

Salin/timpa file sesuai path ke repo, lalu:

```bash
git add .
git commit -m "revise changelog landing rating and privacy"
git push origin master
```

GitHub Actions akan menjalankan auto-deploy sesuai workflow yang sudah digunakan.
