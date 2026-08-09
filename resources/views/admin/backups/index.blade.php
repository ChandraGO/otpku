@extends('layouts.app')
@php
    $title = 'Cadangan Basis Data';
@endphp
@section('content')
<div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.22em] text-violet-500">Keamanan data</p>
        <h1 class="mt-1 text-3xl font-black">Cadangan basis data</h1>
        <p class="mt-2 text-sm text-slate-500">Buat, unggah, unduh, pulihkan, atau hapus beberapa file backup sekaligus.</p>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="font-bold">Buat cadangan baru</h2>
        <p class="mt-2 text-sm text-slate-500">Berkas dikompresi gzip dan diberi checksum SHA-256.</p>
        <form class="mt-4" method="post" action="{{ route('admin.backups.create') }}">
            @csrf
            <button class="btn-primary">Buat cadangan sekarang</button>
        </form>
    </section>

    <section class="card p-6">
        <h2 class="font-bold">Unggah cadangan</h2>
        <form class="mt-4 flex flex-col gap-3 sm:flex-row" method="post" enctype="multipart/form-data" action="{{ route('admin.backups.upload') }}">
            @csrf
            <input class="input" type="file" name="backup" accept=".sql,.gz,.sql.gz" required>
            <button class="btn-secondary">Unggah</button>
        </form>
    </section>
</div>

<form id="bulk-delete-form" method="post" action="{{ route('admin.backups.bulk-destroy') }}" onsubmit="return confirmBulkBackupDelete()">
    @csrf
    @method('delete')
</form>

<div class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-bold">
            <input id="backup-select-all" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
            Pilih semua di halaman ini
        </label>
        <span id="backup-selected-count" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500 dark:bg-white/5">0 dipilih</span>
    </div>
    <button id="backup-bulk-delete-button" form="bulk-delete-form" type="submit" class="btn-danger !px-4 !py-2.5 disabled:cursor-not-allowed disabled:opacity-40" disabled>
        Hapus yang dipilih
    </button>
</div>

<div class="mt-4 table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th class="w-12 text-center">Pilih</th>
                <th>Berkas</th>
                <th>Sumber</th>
                <th>Ukuran</th>
                <th>Checksum</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($backups as $backup)
                <tr>
                    <td class="text-center">
                        <input
                            class="backup-select h-4 w-4 cursor-pointer rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            type="checkbox"
                            name="backups[]"
                            value="{{ $backup->id }}"
                            form="bulk-delete-form"
                            aria-label="Pilih {{ $backup->filename }}"
                        >
                    </td>
                    <td class="font-semibold">{{ $backup->filename }}</td>
                    <td>{{ $backup->source }}</td>
                    <td>{{ number_format($backup->size / 1024 / 1024, 2) }} MB</td>
                    <td class="max-w-44 truncate">{{ $backup->checksum }}</td>
                    <td>{{ $backup->created_at->format('d M Y H:i') }}</td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            <a class="btn-secondary !px-3 !py-2" href="{{ route('admin.backups.download', $backup) }}">Unduh</a>

                            <details>
                                <summary class="btn-danger cursor-pointer !px-3 !py-2">Pulihkan</summary>
                                <form class="absolute right-8 z-20 mt-2 w-72 rounded-xl border border-rose-500/30 bg-white p-4 shadow-xl dark:bg-slate-900" method="post" action="{{ route('admin.backups.restore', $backup) }}">
                                    @csrf
                                    <input class="input" name="confirmation" placeholder="Ketik RESTORE" required>
                                    <div class="mt-2">
                                        <x-password-input name="password" autocomplete="current-password" placeholder="Kata sandi admin" required />
                                    </div>
                                    <button class="btn-danger mt-2 w-full">Pulihkan basis data</button>
                                </form>
                            </details>

                            <form method="post" action="{{ route('admin.backups.destroy', $backup) }}" onsubmit="return confirm('Hapus cadangan ini?')">
                                @csrf
                                @method('delete')
                                <button class="btn-secondary !px-3 !py-2">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-10 text-center">Belum ada cadangan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $backups->links() }}</div>

<script>
(() => {
    const selectAll = document.getElementById('backup-select-all');
    const items = Array.from(document.querySelectorAll('.backup-select'));
    const count = document.getElementById('backup-selected-count');
    const button = document.getElementById('backup-bulk-delete-button');

    if (!selectAll || !count || !button) return;

    const sync = () => {
        const selected = items.filter((item) => item.checked).length;
        count.textContent = `${selected} dipilih`;
        button.disabled = selected === 0;
        selectAll.checked = items.length > 0 && selected === items.length;
        selectAll.indeterminate = selected > 0 && selected < items.length;
    };

    selectAll.addEventListener('change', () => {
        items.forEach((item) => { item.checked = selectAll.checked; });
        sync();
    });

    items.forEach((item) => item.addEventListener('change', sync));
    sync();

    window.confirmBulkBackupDelete = () => {
        const selected = items.filter((item) => item.checked).length;
        if (selected === 0) return false;

        return window.confirm(`Hapus ${selected} file backup yang dipilih? Tindakan ini tidak dapat dibatalkan.`);
    };
})();
</script>
@endsection
