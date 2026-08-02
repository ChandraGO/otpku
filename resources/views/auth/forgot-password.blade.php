@extends('layouts.guest')
@php($title = 'Lupa Password')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12"><div class="card w-full p-6 sm:p-8"><h1 class="text-2xl font-black">Reset password</h1><p class="mt-2 text-sm text-slate-500">Kami akan mengirim kode reset ke email terdaftar.</p><x-flash /><form class="mt-7 space-y-4" method="post" action="{{ route('password.email') }}">@csrf<div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus></div><button class="btn-primary w-full">Kirim kode reset</button></form></div></section>
@endsection
