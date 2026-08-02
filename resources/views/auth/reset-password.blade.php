@extends('layouts.guest')
@php($title = 'Password Baru')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12"><div class="card w-full p-6 sm:p-8"><h1 class="text-2xl font-black">Buat password baru</h1><x-flash /><form class="mt-7 space-y-4" method="post" action="{{ route('password.update') }}">@csrf<div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ $email }}" required></div><div><label class="label">Kode OTP</label><input class="input" name="code" maxlength="6" inputmode="numeric" required></div><div><label class="label">Password baru</label><input class="input" type="password" name="password" required></div><div><label class="label">Ulangi password</label><input class="input" type="password" name="password_confirmation" required></div><button class="btn-primary w-full">Simpan password</button></form></div></section>
@endsection
