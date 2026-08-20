# Revisi Scroll Assembly Home V6

Fokus revisi ini mengikuti feedback screenshot terakhir.

## Yang diubah

- Badge `API KEY`, `OTP LIVE`, `SALDO`, `200 OK`, dan `MULTI SERVER` sekarang sudah berada di area kosong hero sejak state awal desktop.
- Refresh tidak menjalankan entrance animation. Badge hanya diam pada posisi scatter; geraknya baru mengikuti scroll.
- Saat scroll turun, hero text naik + blur, sementara badge ikut bergerak turun/mendekat ke area assembly.
- API card tidak muncul saat refresh. Card baru naik dari bawah setelah scroll berjalan dan hero mulai meninggalkan layar.
- Badge merakit ke posisi final secara bertahap sampai sekitar 90% progress scroll.
- Posisi final diturunkan agar card memenuhi bagian tengah-bawah viewport dan tidak meninggalkan area kosong besar di bawah.
- Tinggi scroll scene dipangkas dari sekitar 180–190vh menjadi sekitar 160–165svh/vh.
- State final hanya ditahan sebentar (sekitar 90–100%) lalu section `CONTOH REQUEST` langsung masuk.
- Mobile tetap memiliki assembly scroll; badge mulai muncul setelah user mulai scroll agar tidak menutupi copy pada layar sempit.

## File

- `frontend/src/pages/Landing.jsx`
