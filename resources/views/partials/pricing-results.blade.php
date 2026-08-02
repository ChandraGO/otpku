<div class="mt-8 space-y-3 lg:hidden">
    @forelse($services as $service)
        <article class="service-row">
            <x-service-icon :service="$service" />
            <div class="min-w-0 flex-1">
                <div class="truncate font-black">{{ trim($service->name) }}</div>
                <div class="mt-1 text-xs text-slate-500">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }} – Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</div>
                <div class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-300">Stok {{ number_format((int) $service->total_stock) }}</div>
            </div>
            @auth
                <a href="{{ route('services.show', $service) }}" class="btn-primary !px-3 !py-2">Pilih</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary !px-3 !py-2">Masuk</a>
            @endauth
        </article>
    @empty
        <div class="card p-10 text-center text-slate-500">Layanan tidak ditemukan.</div>
    @endforelse
</div>

<div class="table-wrap mt-8 hidden lg:block">
    <table class="table">
        <thead><tr><th>Layanan</th><th>Harga terendah</th><th>Harga tertinggi</th><th>Stok</th><th></th></tr></thead>
        <tbody>
        @forelse($services as $service)
            <tr>
                <td><div class="flex items-center gap-3"><x-service-icon :service="$service" size="sm" /><span class="font-bold">{{ trim($service->name) }}</span></div></td>
                <td class="font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</td>
                <td>{{ number_format((int) $service->total_stock) }} pcs</td>
                <td class="text-right">
                    @auth<a href="{{ route('services.show', $service) }}" class="btn-secondary !px-3 !py-2">Pilih</a>
                    @else<a href="{{ route('login') }}" class="btn-secondary !px-3 !py-2">Login</a>@endauth
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-12 text-center text-slate-500">Layanan tidak ditemukan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-8" data-live-pagination>{{ $services->links() }}</div>
