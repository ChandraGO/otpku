@extends('layouts.guest')
@php($title = 'Verifikasi Email')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12"><div class="card w-full p-6 text-center sm:p-8"><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-400/10 text-2xl">✉</span><h1 class="mt-5 text-2xl font-black">Verifikasi email</h1><div class="mt-2 text-sm text-slate-500">Kode dikirim ke <strong>{{ auth()->user()->email }}</strong>.</div><x-flash /><form class="mt-7" method="post" action="{{ route('verification.verify') }}">@csrf<input class="input text-center text-2xl font-black tracking-[.45em]" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus><button class="btn-primary mt-4 w-full">Verifikasi</button></form><form class="mt-3" method="post" action="{{ route('verification.send') }}">@csrf<button class="btn-secondary w-full">Kirim ulang kode</button></form></div></section>
@endsection
