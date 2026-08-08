@extends('layouts.guest')
@php($title = 'Lupa Kata Sandi')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12"><div class="card w-full p-6 sm:p-8"><h1 class="text-2xl font-black">Atur ulang kata sandi</h1><x-flash /><form class="mt-7 space-y-4" method="post" action="{{ route('password.email') }}">@csrf<div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus></div><button class="btn-primary w-full">Kirim kode pengaturan ulang</button></form></div></section>
@endsection
