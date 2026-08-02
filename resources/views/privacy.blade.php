@extends('layouts.guest')
@php($title = 'Kebijakan Privasi')
@section('content')
<article class="mx-auto max-w-3xl px-4 py-16 sm:px-6"><div class="card p-6 sm:p-10"><h1 class="text-3xl font-black">Kebijakan Privasi</h1><div class="mt-6 space-y-5 text-sm leading-7 text-slate-600 dark:text-slate-400"><p>Kami menyimpan data akun, riwayat transaksi, catatan keamanan, serta data teknis yang diperlukan untuk menjalankan layanan dan menangani sengketa. Password disimpan sebagai hash, sedangkan rahasia integrasi dan kode OTP sensitif disimpan terenkripsi pada sisi server.</p><p>Kunci API provider tidak dikirim ke browser. Data dapat diteruskan ke penyedia SMS dan pembayaran hanya sejauh yang diperlukan untuk memenuhi transaksi Anda.</p><p>Log keamanan dan transaksi dapat disimpan untuk pencegahan penyalahgunaan, audit, pencadangan, dan kepatuhan. Hubungi dukungan untuk permintaan terkait data yang diizinkan oleh hukum.</p></div></div></article>
@endsection
