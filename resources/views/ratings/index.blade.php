@extends('layouts.guest')
@php($title = 'Rating & Review')
@section('content')
<section class="page-grid border-b border-slate-200 py-14 dark:border-white/10 sm:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="grid items-center gap-8 lg:grid-cols-[1fr_auto]">
            <div>
                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Pengalaman pengguna</span>
                <h1 class="mt-5 max-w-3xl text-4xl font-black tracking-tight text-slate-950 dark:text-white sm:text-5xl">Rating & review {{ $siteName }}</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-400 sm:text-base">Review hanya dapat dikirim oleh pengguna yang sudah menyelesaikan minimal satu transaksi OTP.</p>
            </div>
            <div class="card flex min-w-[220px] items-center gap-4 p-5 sm:p-6">
                <div class="grid size-14 place-items-center rounded-2xl bg-amber-100 text-2xl font-black text-amber-600 dark:bg-amber-400/10 dark:text-amber-300">★</div>
                <div>
                    <div class="text-3xl font-black text-slate-950 dark:text-white">{{ number_format((float) $ratingAverage, 1, ',', '.') }}</div>
                    <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ number_format((int) $ratingCount) }} review terverifikasi</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
    <x-flash />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div>
            <div class="grid gap-5 md:grid-cols-2">
                @forelse($ratings as $item)
                    @php($avatarUrl = $item->user?->emailAvatarUrl(96))
                    <article class="card flex h-full flex-col p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                @if(filled($avatarUrl))
                                    <span class="size-11 shrink-0 overflow-hidden rounded-2xl bg-slate-100 dark:bg-white/5" data-rating-avatar>
                                        <img src="{{ $avatarUrl }}" alt="Foto {{ $item->user?->name ?: 'pengguna' }}" class="h-full w-full object-cover" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('[data-rating-avatar]')?.remove()">
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate font-black text-slate-950 dark:text-white">{{ $item->user?->name ?: $item->user?->username ?: 'Pengguna' }}</div>
                                    <div class="mt-1 text-[11px] font-semibold text-slate-400">{{ $item->updated_at?->translatedFormat('d M Y · H:i') }}</div>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Transaksi selesai</span>
                        </div>
                        <div class="mt-5 flex items-center gap-1" aria-label="{{ $item->rating }} dari 5 bintang">
                            @for($star = 1; $star <= 5; $star++)
                                <span class="text-lg {{ $star <= $item->rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-700' }}">★</span>
                            @endfor
                        </div>
                        <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $item->review }}</p>
                    </article>
                @empty
                    <div class="card md:col-span-2 p-10 text-center">
                        <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-100 text-2xl text-amber-600 dark:bg-amber-400/10 dark:text-amber-300">★</div>
                        <h2 class="mt-4 text-xl font-black">Belum ada rating</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Jadilah pengguna pertama yang membagikan pengalaman setelah transaksi selesai.</p>
                    </div>
                @endforelse
            </div>

            @if($ratings->hasPages())
                <div class="mt-8">{{ $ratings->links() }}</div>
            @endif
        </div>

        <aside class="lg:sticky lg:top-28 lg:self-start">
            @auth
                @if($canRate)
                    <form method="POST" action="{{ route('ratings.store') }}" class="card p-6" data-rating-form>
                        @csrf
                        <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $userRating ? 'Perbarui review' : 'Beri rating' }}</span>
                        <h2 class="mt-4 text-2xl font-black">Bagaimana pengalaman Anda?</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Rating Anda akan tampil secara publik bersama nama, foto akun bila tersedia, serta waktu review.</p>

                        @php($selectedRating = (int) old('rating', $userRating?->rating ?? 5))
                        <div class="mt-6">
                            <label class="text-sm font-black">Rating</label>
                            <div class="mt-2 flex gap-1" data-rating-stars>
                                @for($star = 1; $star <= 5; $star++)
                                    <button type="button" class="rating-star-button {{ $star <= $selectedRating ? 'is-active' : '' }}" data-rating-value="{{ $star }}" aria-label="{{ $star }} bintang">★</button>
                                @endfor
                            </div>
                            <input type="hidden" name="rating" value="{{ $selectedRating }}" data-rating-input>
                        </div>

                        <label class="mt-5 block text-sm font-black" for="rating-review">Review</label>
                        <textarea id="rating-review" name="review" rows="6" maxlength="1200" class="input mt-2 resize-y" placeholder="Ceritakan pengalaman transaksi OTP Anda..." required>{{ old('review', $userRating?->review) }}</textarea>
                        <div class="mt-2 text-xs text-slate-400">Minimal 10 karakter · Maksimal 1.200 karakter</div>

                        <button type="submit" class="btn-primary mt-6 w-full justify-center">{{ $userRating ? 'Simpan perubahan' : 'Kirim rating' }}</button>
                    </form>
                @else
                    <div class="card p-6">
                        <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">Belum memenuhi syarat</span>
                        <h2 class="mt-4 text-xl font-black">Selesaikan 1 transaksi dulu</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Setelah minimal satu pesanan berstatus selesai, formulir rating akan terbuka otomatis.</p>
                        <a href="{{ route('services.index') }}" class="btn-primary mt-5 w-full justify-center">Pilih layanan</a>
                    </div>
                @endif
            @else
                <div class="card p-6">
                    <span class="badge bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Untuk pelanggan</span>
                    <h2 class="mt-4 text-xl font-black">Punya pengalaman dengan {{ $siteName }}?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Masuk terlebih dahulu. Formulir rating tersedia bagi akun yang sudah memiliki minimal satu transaksi selesai.</p>
                    <a href="{{ route('login') }}" class="btn-primary mt-5 w-full justify-center">Masuk untuk beri rating</a>
                </div>
            @endauth
        </aside>
    </div>
</section>

@push('head')
<style>
    .rating-star-button { display:grid; width:2.55rem; height:2.55rem; place-items:center; border-radius:.85rem; border:1px solid rgb(226 232 240); background:rgb(248 250 252); color:rgb(203 213 225); font-size:1.45rem; line-height:1; transition:transform .16s ease,border-color .16s ease,background .16s ease,color .16s ease; }
    .rating-star-button:hover { transform:translateY(-2px); border-color:rgb(251 191 36); color:rgb(251 191 36); }
    .rating-star-button.is-active { border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.10); color:rgb(245 158 11); }
    .dark .rating-star-button { border-color:rgba(255,255,255,.10); background:rgba(255,255,255,.035); color:rgb(71 85 105); }
    .dark .rating-star-button.is-active { border-color:rgba(251,191,36,.28); background:rgba(245,158,11,.10); color:rgb(251 191 36); }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-rating-form]');
    if (!root) return;
    const input = root.querySelector('[data-rating-input]');
    const buttons = Array.from(root.querySelectorAll('[data-rating-value]'));
    const paint = (value) => buttons.forEach((button) => button.classList.toggle('is-active', Number(button.dataset.ratingValue) <= value));
    buttons.forEach((button) => button.addEventListener('click', () => {
        const value = Number(button.dataset.ratingValue || 0);
        if (!value || !input) return;
        input.value = String(value);
        paint(value);
    }));
})();
</script>
@endpush
@endsection
