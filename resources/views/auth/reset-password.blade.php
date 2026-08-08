@extends('layouts.guest')
@php($title = 'Kata Sandi Baru')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-black">Buat kata sandi baru</h1>
        <x-flash />
        <form class="mt-7 space-y-4" method="post" action="{{ route('password.update') }}">
            @csrf
            <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ $email }}" autocomplete="email" required></div>
            <div><label class="label">Kode OTP</label><input class="input" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required></div>
            <div><label class="label">Kata sandi baru</label><x-password-input name="password" autocomplete="new-password" required /></div>
            <div><label class="label">Ulangi kata sandi</label><x-password-input name="password_confirmation" autocomplete="new-password" required /></div>
            <button class="btn-primary w-full">Simpan kata sandi</button>
        </form>
    </div>
</section>
@endsection
