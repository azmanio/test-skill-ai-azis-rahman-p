@extends('layouts.app')

@section('title', 'Katalog')

@section('content')
    <h1>Katalog Emas</h1>

    <div class="product-grid">
        @foreach($products as $product)
            <div class="product-card">
                <h2>{{ $product->name }}</h2>
                <div class="product-meta">
                    <span class="price"><small>Rp</small>{{ number_format($product->price, 0, ',', '.') }}</span>
                    @if($product->stock > 0)
                        <span class="chip chip-stock">Stok {{ $product->stock }}</span>
                    @else
                        <span class="chip chip-out">Habis</span>
                    @endif
                </div>

                @if($product->stock > 0)
                    <form method="POST" action="{{ route('web.checkout') }}"
                          class="checkout-form" data-stock="{{ $product->stock }}"
                          data-name="{{ $product->name }}" novalidate>
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}" />
                        <div class="qty-row">
                            <label for="quantity-{{ $product->id }}">Jumlah</label>
                            <input
                                type="number"
                                id="quantity-{{ $product->id }}"
                                name="quantity"
                                min="1"
                                max="{{ $product->stock }}"
                                value="1"
                                required
                            />
                        </div>
                        <button type="submit" class="btn">Checkout</button>
                    </form>
                @else
                    <p class="out-of-stock">Stok habis — tidak dapat dibeli</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    // Validasi jumlah di sisi klien sebelum submit — feedback instan, tanpa reload.
    document.querySelectorAll('.checkout-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var input = form.querySelector('input[name="quantity"]');
            var stock = parseInt(form.dataset.stock, 10);
            var qty = parseInt(input.value, 10);

            if (!input.value || isNaN(qty) || qty < 1 || !Number.isInteger(qty)) {
                e.preventDefault();
                toast('warning', 'Jumlah tidak valid', 'Masukkan bilangan bulat positif (minimal 1).');
            } else if (qty > stock) {
                e.preventDefault();
                toast('error', 'Stok tidak mencukupi', 'Stok tersedia untuk ' + form.dataset.name + ' hanya ' + stock + ' gram.');
            }
        });
    });

    @if(session('error'))
        toast('error', 'Checkout gagal', {{ json_encode(session('error')) }});
    @endif
    @if($errors->any())
        toast('error', 'Checkout gagal', {{ json_encode($errors->first()) }});
    @endif
    @if(session('success'))
        toast('success', 'Berhasil', {{ json_encode(session('success')) }});
    @endif
</script>
@endpush