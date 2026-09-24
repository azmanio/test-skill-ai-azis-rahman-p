<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService)
    {
    }

    public function showCatalog(): View
    {
        $products = Product::all();

        return view('catalog', compact('products'));
    }

    public function showOrder(string $orderNumber): View
    {
        $order = Order::with('product')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('order-detail', compact('order'));
    }

    /**
     * JSON checkout (used by tests and API clients).
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $order = $this->checkoutService->checkout(
            (int) $validated['product_id'],
            (int) $validated['quantity'],
        );

        return response()->json($order->load('product'), 201);
    }

    /**
     * Web form checkout → redirect to order detail.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        try {
            $order = $this->checkoutService->checkout(
                (int) $validated['product_id'],
                (int) $validated['quantity'],
            );
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order->order_number)
            ->with('success', 'Order created. Please complete payment.');
    }
}