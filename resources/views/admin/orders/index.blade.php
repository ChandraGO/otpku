@extends('layouts.app')
@php($title = 'Semua Pesanan')
@php($statusLabels = ['processing'=>'Diproses','pending'=>'Menunggu','ready'=>'Siap','completed'=>'Selesai','cancelled'=>'Dibatalkan','expired'=>'Kedaluwarsa','refunded'=>'Dikembalikan','failed'=>'Gagal'])
@section('content')
<div><h1 class="text-3xl font-black">Semua pesanan</h1></div>
<form class="card mt-6 grid gap-3 p-4 sm:grid-cols-3" method="get" data-auto-filter>
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Layanan, nomor, ID aktivasi">
    <select class="input" name="status"><option value="">Semua status</option>@foreach($statusLabels as $status=>$label)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $label }}</option>@endforeach</select>
    <button class="btn-primary">Cari</button>
</form>
<div class="mt-6 table-wrap"><table class="table"><thead><tr><th>Pengguna</th><th>Layanan</th><th>Nomor</th><th>Status</th><th>Harga</th><th>Waktu</th><th></th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->user?->email }}</td><td>{{ $order->service_name }}<div class="text-xs text-slate-500">{{ $order->country_name }}</div></td><td>{{ $order->phone_number ?: '—' }}</td><td><x-status :value="$order->status" /></td><td>Rp {{ number_format((float)$order->sell_price,0,',','.') }}</td><td>{{ $order->created_at->format('d M H:i') }}</td><td><a class="btn-secondary !px-3 !py-2" href="{{ route('admin.orders.show',$order) }}">Detail</a></td></tr>@empty<tr><td colspan="7" class="py-10 text-center">Tidak ada pesanan.</td></tr>@endforelse</tbody></table></div><div class="mt-6">{{ $orders->links() }}</div>
@endsection
