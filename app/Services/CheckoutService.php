<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Checkout business logic. Shared by web form and API endpoint.
 *
 * Transaction boundary: lock product row → validate stock → snapshot price
 * → create order → decrement stock → COMMIT. Any failure rolls back everything.
 */
final class CheckoutService
{
    /**
     * @return Order created pending order
     *
     * @throws HttpException 409 when stock insufficient
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when product missing
     */
    public function checkout(int $productId, int $quantity): Order
    {
        return DB::transaction(function () use ($productId, $quantity) {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock < $quantity) {
                throw new HttpException(409, 'Stok tidak mencukupi');
            }

            $checkoutPrice = $product->price; // price snapshot, never from client
            $total = $checkoutPrice * $quantity;

            $order = Order::create([
                'order_number'    => 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'product_id'      => $product->id,
                'quantity'        => $quantity,
                'checkout_price'  => $checkoutPrice,
                'total'           => $total,
                'status'          => Order::STATUS_PENDING,
            ]);

            $product->decrement('stock', $quantity);

            return $order;
        });
    }
}