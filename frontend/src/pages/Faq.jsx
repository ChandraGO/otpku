import React from "react";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

const QA = [
  ["Bagaimana cara mulai?", "Daftar akun, verifikasi email lewat kode OTP, isi saldo melalui pembayaran otomatis, lalu pesan nomor dari dasbor."],
  ["Berapa lama nomor aktif?", "Masa aktif pesanan diatur admin (default 15 menit). Jika OTP tidak masuk sampai kedaluwarsa, saldo dikembalikan otomatis."],
  ["Apakah ada API?", "Ya. Setiap akun punya API key sendiri. Lihat halaman Docs untuk endpoint deposit, order, dan cek status."],
  ["Bagaimana isi saldo bekerja?", "Buat pembayaran dari dasbor. Setelah pembayaran terdeteksi, saldo bertambah otomatis."],
  ["Kenapa harus klik Siap?", "Tombol Siap memberi tahu sistem bahwa nomor mulai dipakai untuk registrasi, sehingga OTP diteruskan ke dasbor."],
  ["Apakah kirim ulang OTP berbayar?", "Tidak. Kirim ulang gratis selama masih dalam batas waktu pesanan."],
  ["Bagaimana jika butuh bantuan?", "Buat tiket dari dasbor. Tiket diteruskan ke email operator dan bot Telegram sehingga cepat ditangani."],
];

export default function Faq() {
  return (
    <div data-testid="faq-page" className="mx-auto max-w-3xl px-6 py-14">
      <h1 className="text-4xl font-extrabold sm:text-5xl">FAQ</h1>
      <Accordion type="single" collapsible className="mt-8">
        {QA.map(([q, a], i) => (
          <AccordionItem key={q} value={`i${i}`} className="rounded-2xl border border-border bg-card px-5 mb-3">
            <AccordionTrigger data-testid={`faq-q-${i}`} className="text-left text-sm font-bold hover:no-underline">{q}</AccordionTrigger>
            <AccordionContent className="text-sm text-muted-foreground">{a}</AccordionContent>
          </AccordionItem>
        ))}
      </Accordion>
    </div>
  );
}
