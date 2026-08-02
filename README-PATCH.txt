PATCH REVISI UI + EMAIL OTP
Tanggal: 2026-08-03

Cara pasang:
1. Ekstrak seluruh isi ZIP ini ke root repository KodeOTP.
2. Timpa file lama bila diminta.
3. Pastikan APP_URL pada environment VPS berisi domain website yang benar.
4. Commit lalu push ke branch main/master.
5. Workflow deploy akan menjalankan deploy incremental; patch ini tidak memerlukan npm install atau rebuild asset Vite karena interaksi utama memakai runtime inline mandiri.

Perubahan:
- Toggle dark/light benar-benar aktif dan hanya menampilkan satu ikon.
- Default tema tetap dark; pilihan disimpan di browser dan akun login.
- Tombol mata password aktif dan hanya menampilkan satu ikon.
- Pencarian harga otomatis saat mengetik, tetap menyediakan tombol Cari.
- Hasil layanan tetap memiliki tombol Login/Pilih.
- Menu mobile dan sidebar tidak bergantung pada Alpine untuk fungsi dasar.
- Email OTP memakai template penuh warna.
- Penutup email: OTPKU JagPro.
- Domain website otomatis diambil dari APP_URL dan ditampilkan pada email.

Catatan:
Jika domain yang tampil pada email salah, perbaiki APP_URL pada /opt/kodeotp/app.env atau file environment aplikasi di VPS, lalu redeploy/restart aplikasi.
