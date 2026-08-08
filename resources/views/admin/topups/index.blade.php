@extends('layouts.app')
@php($title = 'Pembayaran Isi Saldo')
@php($statusLabels = ['creating'=>'Membuat transaksi','pending'=>'Menunggu','completed'=>'Selesai','expired'=>'Kedaluwarsa','cancelled'=>'Dibatalkan','failed'=>'Gagal'])
@section('content')
<div><h1 class="text-3xl font-black">Pembayaran isi saldo</h1></div>
<form class="card mt-6 flex gap-3 p-4" method="get" data-auto-filter>
    <select class="input" name="status"><option value="">Semua status</option>@foreach($statusLabels as $status=>$label)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $label }}</option>@endforeach</select>
</form>
<div class="mt-6 table-wrap"><table class="table"><thead><tr><th>ID pesanan</th><th>Pengguna</th><th>Nominal</th><th>Metode</th><th>Status</th><th>Waktu</th><th></th></tr></thead><tbody>@forelse($topups as $topup)<tr><td>{{ $topup->order_id }}</td><td>{{ $topup->user?->email }}</td><td>Rp {{ number_format((float)$topup->amount,0,',','.') }}</td><td>{{ strtoupper(str_replace('_',' ',$topup->payment_method)) }}</td><td><x-status :value="$topup->status" /></td><td>{{ $topup->created_at->format('d M H:i') }}</td><td><a class="btn-secondary !px-3 !py-2" href="{{ route('admin.topups.show',$topup) }}">Detail</a></td></tr>@empty<tr><td colspan="7" class="py-10 text-center">Tidak ada isi saldo.</td></tr>@endforelse</tbody></table></div><div class="mt-6">{{ $topups->links() }}</div>
@endsection
