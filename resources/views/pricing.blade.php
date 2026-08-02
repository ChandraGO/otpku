@extends('layouts.guest')
@php($title = 'Harga layanan')
@section('content')
<section class="page-grid min-h-[75vh] py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="max-w-3xl">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Transparent Pricing</span>
            <h1 class="text-balance mt-5 text-4xl font-black tracking-tight sm:text-5xl">Harga layanan OTP terbaru</h1>
            <p class="mt-4 text-lg leading-8 text-slate-500 dark:text-slate-400">Bandingkan harga dan ketersediaan layanan dalam Rupiah sebelum melakukan pemesanan.</p>
        </div>

        <form
            action="{{ route('pricing') }}"
            method="get"
            data-live-service-search
            class="card mt-9 flex flex-col gap-3 p-4 sm:flex-row"
        >
            <label class="relative flex-1">
                <span class="sr-only">Cari layanan</span>
                <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-cyan-500" />
                <input
                    class="input !pl-12"
                    name="q"
                    value="{{ request('q') }}"
                    data-service-query
                    autocomplete="off"
                    placeholder="Cari layanan..."
                    aria-label="Cari layanan"
                >
            </label>
            <select class="input sm:max-w-xs" name="sort" data-service-sort aria-label="Urutkan layanan">
                <option value="popular" @selected(request('sort', 'popular') === 'popular')>Rekomendasi</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Harga termurah</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Harga tertinggi</option>
                <option value="name" @selected(request('sort') === 'name')>Nama A–Z</option>
            </select>
            <button type="submit" class="btn-primary min-w-28" data-service-search-button>
                <span data-service-search-spinner hidden class="size-4 animate-spin rounded-full border-2 border-white/35 border-t-white" aria-hidden="true"></span>
                <span>Cari</span>
            </button>
        </form>

        <p class="mt-3 text-xs text-slate-500">Hasil otomatis diperbarui saat Anda mengetik. Tombol Cari tetap dapat digunakan kapan saja.</p>

        <div data-service-results class="transition-opacity" aria-live="polite">
            @include('partials.pricing-results', ['services' => $services])
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-live-service-search]');
    const results = document.querySelector('[data-service-results]');
    if (!form || !results) return;

    const queryInput = form.querySelector('[data-service-query]');
    const sortSelect = form.querySelector('[data-service-sort]');
    const button = form.querySelector('[data-service-search-button]');
    const spinner = form.querySelector('[data-service-search-spinner]');
    let debounceTimer;
    let requestController;

    const setLoading = (loading) => {
        results.style.opacity = loading ? '0.55' : '1';
        results.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (spinner) spinner.hidden = !loading;
        if (button) button.disabled = loading;
    };

    const buildUrl = (sourceUrl = null, partial = true) => {
        const url = new URL(sourceUrl || form.action, window.location.origin);
        const query = queryInput?.value.trim() || '';
        const sort = sortSelect?.value || 'popular';

        if (query) url.searchParams.set('q', query);
        else url.searchParams.delete('q');

        if (sort && sort !== 'popular') url.searchParams.set('sort', sort);
        else url.searchParams.delete('sort');

        if (partial) url.searchParams.set('partial', '1');
        else url.searchParams.delete('partial');

        return url;
    };

    const search = async (sourceUrl = null) => {
        requestController?.abort();
        const controller = new AbortController();
        requestController = controller;
        setLoading(true);

        try {
            const requestUrl = buildUrl(sourceUrl, true);
            const response = await fetch(requestUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            results.innerHTML = await response.text();

            const browserUrl = buildUrl(sourceUrl, false);
            window.history.replaceState({}, '', browserUrl);
        } catch (error) {
            if (error.name !== 'AbortError') {
                form.submit();
            }
        } finally {
            if (requestController === controller) setLoading(false);
        }
    };

    queryInput?.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => search(), 250);
    });

    sortSelect?.addEventListener('change', () => search());

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(debounceTimer);
        search();
    });

    results.addEventListener('click', (event) => {
        const link = event.target.closest('[data-live-pagination] a');
        if (!link) return;
        event.preventDefault();
        search(link.href);
    });
})();
</script>
@endpush
