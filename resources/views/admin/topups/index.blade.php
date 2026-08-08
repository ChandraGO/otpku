@extends('layouts.app')
@php($title = 'Pembayaran Isi Saldo')
@php($statusLabels = ['creating'=>'Membuat transaksi','pending'=>'Menunggu','completed'=>'Selesai','expired'=>'Kedaluwarsa','cancelled'=>'Dibatalkan','failed'=>'Gagal'])
@section('content')
<div>
    <h1 class="text-3xl font-black">Pembayaran isi saldo</h1>
    <p class="mt-2 text-sm text-slate-500">Invoice tanpa proses pembayaran yang dapat dipakai atau yang melewati batas waktunya otomatis ditutup sebagai Kedaluwarsa.</p>
</div>
<form class="card mt-6 grid gap-3 p-4 sm:grid-cols-2" method="get" data-auto-filter>
    <select class="input" name="status"><option value="">Semua status</option>@foreach($statusLabels as $status=>$label)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $label }}</option>@endforeach</select>
    <select class="input" name="gateway"><option value="">Semua penyedia pembayaran</option><option value="pakasir" @selected(request('gateway')==='pakasir')>Pakasir</option><option value="duitku" @selected(request('gateway')==='duitku')>Duitku</option></select>
</form>
<div class="mt-6 table-wrap">
    <table class="table">
        <thead><tr><th>ID pesanan</th><th>Pengguna</th><th>Nominal</th><th>Penyedia</th><th>Metode</th><th>Status</th><th>Alasan / catatan</th><th>Waktu</th><th></th></tr></thead>
        <tbody>
            @forelse($topups as $topup)
                <tr>
                    <td>{{ $topup->order_id }}</td>
                    <td>{{ $topup->user?->email }}</td>
                    <td>Rp {{ number_format((float)$topup->amount,0,',','.') }}</td>
                    <td>{{ ucfirst($topup->gateway ?: 'pakasir') }}</td>
                    <td>{{ strtoupper(str_replace('_',' ',$topup->payment_method)) }}</td>
                    <td><x-status :value="$topup->status" /></td>
                    <td class="min-w-52 text-xs leading-5 text-slate-500">
                        @if($topup->status === 'cancelled')
                            <div class="font-bold text-slate-700 dark:text-slate-200">{{ $topup->cancellationReasonLabel() ?: 'Dibatalkan pengguna' }}</div>
                            @if(filled($topup->cancel_note))<div class="mt-1">{{ $topup->cancel_note }}</div>@endif
                        @elseif($topup->status === 'expired')
                            Ditutup otomatis / kedaluwarsa
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $topup->created_at->format('d M H:i') }}</td>
                    <td><a class="btn-secondary !px-3 !py-2" href="{{ route('admin.topups.show',$topup) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-10 text-center">Tidak ada isi saldo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $topups->links() }}</div>
@endsection
