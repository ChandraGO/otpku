# Revisi Scroll Assembly Home V13

Fokus revisi ini hanya menghapus ruang kosong besar setelah final assembly, terutama pada tampilan HP.

## Yang diubah

- Posisi final card dari V12 dipertahankan.
- Smooth scroll `useSpring()` dari V12 dipertahankan.
- Tinggi hero scene sedikit dipangkas:
  - mobile: `144svh` -> `138svh`
  - tablet: `142svh` -> `136svh`
  - desktop: `140vh` -> `134vh`
- Section **Contoh Request** ditarik naik dengan overlap responsif:
  - mobile: `-28svh`
  - tablet: `-24svh`
  - desktop: `-20vh`
- Hero diberi `z-10`, section berikutnya `z-0`, sehingga final assembly tetap tampil utuh di atas transisi dan tidak tertutup background section berikutnya.

## Hasil yang diharapkan

Final assembly tetap di posisi V12, tetapi begitu final tercapai, bagian **Contoh Request** sudah mulai masuk dari bawah. Tidak ada lagi blok kosong panjang di HP maupun desktop.
