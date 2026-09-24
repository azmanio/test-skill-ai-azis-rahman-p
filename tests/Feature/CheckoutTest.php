<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_checkout_creates_order_decreases_stock(): void
    {
        $product = Product::factory()->create([
            'name' => 'Antam 1 gram',
            'price' => 1500000,
            'stock' => 1,
        ]);

        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'product_id' => $product->id,
                     'quantity'   => 1,
                     'status'     => 'pending',
                 ]);

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'stock' => 0,
        ]);
    }

    public function test_insufficient_stock_rejects_checkout(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);

        $response->assertStatus(409)
                 ->assertJson([
                     'message' => 'Stok tidak mencukupi',
                 ]);

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'stock' => 1,
        ]);

        $this->assertDatabaseMissing('orders', [
            'product_id' => $product->id,
        ]);
    }

    public function test_invalid_quantity_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 0,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_decimal_quantity_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1.5,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_negative_quantity_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => -1,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_product_id_rejected(): void
    {
        $response = $this->postJson('/api/checkout', [
            'product_id' => 9999,
            'quantity'   => 1,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_price_manipulation_server_ignores_client_price(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 2,
        ]);

        // Client sends a fake price (and fake total) — server must ignore both.
        $response = $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 1,
            'total'      => 1,
        ]);

        $response->assertStatus(201);

        $order = Order::latest()->first();

        $this->assertEquals(1500000, $order->checkout_price);
        $this->assertEquals(1500000, $order->total);

        $this->assertEquals(1, $product->fresh()->stock);
    }

    public function test_price_snapshot_retained(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 2,
        ]);

        $this->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $order = Order::latest()->first();
        $orderNumber = $order->order_number;

        // Change product price after checkout.
        $product->update(['price' => 1700000]);

        $order = Order::where('order_number', $orderNumber)->first();

        $this->assertEquals(1500000, $order->checkout_price);
        $this->assertEquals(1500000, $order->total);
    }

    public function test_catalog_page_lists_seeded_products(): void
    {
        Product::factory()->create(['name' => 'Antam 1 gram', 'price' => 1500000, 'stock' => 1]);
        Product::factory()->create(['name' => 'UBS 1 gram', 'price' => 1450000, 'stock' => 3]);
        Product::factory()->create(['name' => 'Emasku 0.5 gram', 'price' => 750000, 'stock' => 0]);

        $response = $this->get('/catalog');

        $response->assertStatus(200)
                 ->assertSee('Antam 1 gram')
                 ->assertSee('UBS 1 gram')
                 ->assertSee('Emasku 0.5 gram')
                 ->assertSee('Stok habis');
    }

    public function test_web_checkout_creates_order_and_redirects(): void
    {
        $this->withoutMiddleware();

        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 2,
        ]);

        $response = $this->post('/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $order = Order::latest()->first();

        $response->assertRedirect(route('orders.show', $order->order_number));
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertEquals('pending', $order->status);
    }

    public function test_order_detail_page_displays_order_fields(): void
    {
        $product = Product::factory()->create(['name' => 'Antam 1 gram', 'price' => 1500000, 'stock' => 1]);
        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
        ]);

        $response = $this->get(route('orders.show', $order->order_number));

        $response->assertStatus(200)
                 ->assertSee($order->order_number)
                 ->assertSee('Antam 1 gram')
                 ->assertSee('1.500.000')
                 ->assertSee('pending');
    }
}