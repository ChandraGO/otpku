@php
    $hasErrors = isset($errors) && $errors->any();
    $success = session('success');
    $warning = session('warning');
    $info = session('info');
@endphp

@if ($success)
    <div data-flash-message role="status" class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-300/60 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800 shadow-sm dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-emerald-500 text-white"><x-icon name="check" size="size-4" /></span>
        <div class="min-w-0 flex-1"><div class="font-black">Berhasil</div><div class="mt-0.5 leading-6">{{ $success }}</div></div>
        <button type="button" data-flash-close class="rounded-lg px-2 py-1 text-lg leading-none opacity-60 transition hover:opacity-100" aria-label="Tutup notifikasi">×</button>
    </div>
@endif

@if ($warning)
    <div data-flash-message role="status" class="mb-5 flex items-start gap-3 rounded-2xl border border-amber-300/60 bg-amber-50 px-4 py-3.5 text-sm text-amber-900 shadow-sm dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-amber-500 text-white"><x-icon name="warning" size="size-4" /></span>
        <div class="min-w-0 flex-1"><div class="font-black">Perhatian</div><div class="mt-0.5 leading-6">{{ $warning }}</div></div>
        <button type="button" data-flash-close class="rounded-lg px-2 py-1 text-lg leading-none opacity-60 transition hover:opacity-100" aria-label="Tutup notifikasi">×</button>
    </div>
@endif

@if ($info)
    <div data-flash-message role="status" class="mb-5 flex items-start gap-3 rounded-2xl border border-cyan-300/60 bg-cyan-50 px-4 py-3.5 text-sm text-cyan-900 shadow-sm dark:border-cyan-400/20 dark:bg-cyan-400/10 dark:text-cyan-200">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-cyan-500 text-white"><x-icon name="info" size="size-4" /></span>
        <div class="min-w-0 flex-1"><div class="font-black">Informasi</div><div class="mt-0.5 leading-6">{{ $info }}</div></div>
        <button type="button" data-flash-close class="rounded-lg px-2 py-1 text-lg leading-none opacity-60 transition hover:opacity-100" aria-label="Tutup notifikasi">×</button>
    </div>
@endif

@if ($hasErrors)
    <div data-flash-message role="alert" class="mb-5 flex items-start gap-3 rounded-2xl border border-rose-300/70 bg-rose-50 px-4 py-3.5 text-sm text-rose-900 shadow-sm dark:border-rose-400/25 dark:bg-rose-400/10 dark:text-rose-200">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-rose-500 text-white"><x-icon name="warning" size="size-4" /></span>
        <div class="min-w-0 flex-1">
            <div class="font-black">Tidak dapat melanjutkan</div>
            <ul class="mt-1.5 space-y-1 leading-6">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" data-flash-close class="rounded-lg px-2 py-1 text-lg leading-none opacity-60 transition hover:opacity-100" aria-label="Tutup notifikasi">×</button>
    </div>
@endif
