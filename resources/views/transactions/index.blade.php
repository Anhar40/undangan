@extends('layouts.customer')

@section('title', 'Riwayat Transaksi — ' . config('app.name'))
@section('customer_section', 'Transaksi')

@section('content')
<div class="container">
    <div class="page-head">
        <div>
            <span class="eyebrow">Pembayaran</span>
            <h1>Riwayat Transaksi</h1>
            <p class="muted">Semua pembayaran aktivasi undangan Anda, dari yang terbaru.</p>
        </div>
    </div>

    <div class="catalog-controls">
        <form method="GET" action="{{ route('transactions.index') }}" class="row" style="width:100%">
            <label class="field" style="margin:0;min-width:220px">
                <span>Status transaksi</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($activeStatus === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </label>
            <span class="spacer"></span>
            <span class="muted small">Total {{ $transactions->total() }} transaksi</span>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        @if ($transactions->isEmpty())
            <div class="empty-state" style="border:0">
                <h3>Belum ada transaksi</h3>
                <p class="muted">Pembayaran aktivasi undangan Anda akan muncul di sini.</p>
                <a class="btn btn-primary" href="{{ route('templates.index') }}">Pilih Template</a>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <caption class="sr-only">Daftar riwayat transaksi undangan digital</caption>
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Undangan</th>
                            <th scope="col">Nominal</th>
                            <th scope="col">Metode</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="mono">{{ $transaction->order_id }}</td>
                                <td>{{ $transaction->invitation?->title ?? '—' }}</td>
                                <td>Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                                <td>{{ $transaction->payment_label ?? $transaction->payment_type ?? '—' }}</td>
                                <td>{{ $transaction->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <span class="pill {{ $transaction->transaction_status?->badgeClass() ?? 'pill-light' }}">
                                        {{ $transaction->transaction_status?->value ?? $transaction->transaction_status }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    @if ($transaction->invitation)
                                        <a class="btn btn-secondary btn-small" href="{{ route('invitations.payment.status', $transaction->invitation) }}">Detail</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px">{{ $transactions->links() }}</div>
        @endif
    </div>
</div>
@endsection
