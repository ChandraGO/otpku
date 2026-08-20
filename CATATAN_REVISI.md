# Scroll Assembly Home V12

Fokus revisi ini hanya pada dua feedback terakhir:

1. **Final assembly sedikit dinaikkan** dibanding V11 agar komposisinya lebih pas di tengah viewport, tetapi tidak kembali terlalu tinggi/terpotong.
2. **Gerakan scroll dibuat lebih mulus** dengan `useSpring()` pada progress scroll Framer Motion.

Detail:
- `useSpring(heroProgress)` dipakai sebagai progress visual agar wheel/trackpad tidak terasa patah per tick.
- Animasi tetap reversible: scroll ke atas = animasi mundur.
- Timeline assembly diperpanjang sampai sekitar 72% progress supaya badge menyatu lebih pelan.
- Jarak scroll scene dibuat sedikit lebih panjang untuk memberi cukup ruang animasi, tetapi state final tetap terlihat sepanjang sisa scene.
- `overflow-visible` dari V11 tetap dipertahankan supaya kartu tidak terpotong lagi.
- Posisi final desktop diturunkan maksimal sekitar 175px pada stage (lebih naik daripada V11 yang 258px).
- Tampilan awal hero tidak diubah.
