@extends('layouts.app')

@section('title', 'Detail Pesanan')

@section('content')
    <h1>Detail Pesanan</h1>

    <div class="order-panel">
        <div class="panel-head">
            <div class="order-no">{{ $order->order_number }}</div>
            <div class="order-no-hint">Dibuat {{ $order->created_at->format('d M Y, H:i') }} WIB</div>
        </div>
        <div class="order-rows">
            <div class="order-row">
                <dt>Produk</dt>
                <dd>{{ $order->product->name }}</dd>
            </div>
            <div class="order-row">
                <dt>Jumlah</dt>
                <dd>{{ $order->quantity }} gram</dd>
            </div>
            <div class="order-row">
                <dt>Harga saat checkout</dt>
                <dd>Rp{{ number_format($order->checkout_price, 0, ',', '.') }}</dd>
            </div>
            <div class="order-row">
                <dt>Total</dt>
                <dd>Rp{{ number_format($order->total, 0, ',', '.') }}</dd>
            </div>
            <div class="order-row">
                <dt>Status</dt>
                <dd>
                    <span class="badge {{ $order->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">
                        {{ $order->status === 'paid' ? 'Lunas' : 'Menunggu pembayaran' }}
                    </span>
                </dd>
            </div>
        </div>
    </div>

    @if($order->status === 'pending')
        <div class="payment-box">
            <h2>Simulasi Pembayaran</h2>
            <p class="token-line">Token otomatis terisi (dari <code>PAYMENT_TOKEN</code>).</p>
            <p style="font-size:.85rem;color:var(--muted);margin:.4rem 0 0;">
                Atau kirim manual via API:
            </p>
            <pre>curl -X POST http://localhost:8000/api/mock-payments \
  -H "X-Payment-Token: local-test-token" \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": "evt-{{ $order->id }}",
    "order_number": "{{ $order->order_number }}",
    "amount": {{ $order->total }},
    "status": "paid"
  }'</pre>

            <form class="pay-form" id="pay-form" data-order="{{ $order->order_number }}"
                  data-event="evt-{{ $order->id }}" data-amount="{{ $order->total }}">
                <button type="submit" class="btn">Bayar Sekarang</button>
            </form>
        </div>
    @endif

    <a href="{{ route('catalog') }}" class="back-link">&larr; Kembali ke katalog</a>
@endsection

@push('scripts')
<script>
    var payForm = document.getElementById('pay-form');
    if (payForm) {
        payForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = payForm.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            fetch('/api/mock-payments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Payment-Token': '{{ config("app.payment_token") }}'
                },
                body: JSON.stringify({
                    event_id: payForm.dataset.event,
                    order_number: payForm.dataset.order,
                    amount: parseInt(payForm.dataset.amount, 10),
                    status: 'paid'
                })
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (r) {
                var s = r.data && r.data.status;
                if (s === 'accepted') {
                    toast('success', 'Pembayaran diterima', 'Order ' + payForm.dataset.order + ' kini berstatus Lunas.');
                    setTimeout(function () { location.reload(); }, 1400);
                } else if (s === 'already_processed') {
                    toast('info', 'Sudah diproses', 'Event ini sudah pernah diproses sebelumnya.');
                    setTimeout(function () { location.reload(); }, 1400);
                } else {
                    toast('error', 'Pembayaran ditolak', (r.data && r.data.message) || 'Terjadi kesalahan.');
                }
                btn.disabled = false;
                btn.textContent = 'Bayar Sekarang';
            })
            .catch(function () {
                toast('error', 'Gagal terhubung', 'Periksa koneksi atau coba lagi.');
                btn.disabled = false;
                btn.textContent = 'Bayar Sekarang';
            });
        });
    }
</script>
@endpush