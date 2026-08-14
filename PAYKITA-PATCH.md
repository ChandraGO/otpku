# Patch PayKita + Checkout Marketplace

Perubahan utama:
- Pembelian OTP bisa langsung dibayar via PayKita tanpa top up.
- Pembelian tetap bisa menggunakan saldo akun.
- Isi saldo tetap tersedia, tetapi gateway eksternalnya hanya PayKita QRIS.
- Pakasir/Duitku dihapus dari route dan UI aktif.
- Pesanan PayKita baru dikirim ke provider OTP setelah status PayKita terverifikasi `paid`.
- Bila pembayaran PayKita sudah paid tetapi pesanan provider gagal/dibatalkan dan berhak refund, nilai produk dikreditkan ke saldo akun.
- Webhook PayKita tidak dipercaya sendirian: server selalu verifikasi ulang status order ke REST API PayKita.

## Setelah menimpa file
1. Jalankan `php artisan migrate --force`.
2. Jalankan `php artisan optimize:clear`.
3. Buka Admin > Pengaturan > Pembayaran PayKita dan simpan API key project `pk_live_...`.
4. Pastikan `APP_URL` memakai HTTPS agar webhook order bisa dikirim ke `/webhooks/paykita`.
5. Pastikan queue worker dan scheduler tetap berjalan.

Jangan menaruh API key PayKita di JavaScript/Blade/frontend.
