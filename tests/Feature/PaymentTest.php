<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_payment_accepted(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'accepted',
                 ]);

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt-001',
            'order_id' => $order->id,
            'amount'   => 1500000,
            'status'   => 'paid',
        ]);

        $this->assertEquals('paid', $order->fresh()->status);
    }

    public function test_duplicate_payment_event_idempotent(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        // First payment
        $response1 = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response1->assertStatus(200)
                  ->assertJson(['status' => 'accepted']);

        // Second payment with same event_id
        $response2 = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response2->assertStatus(200)
                  ->assertJson(['status' => 'already_processed']);

        // Only one payment event should exist
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertEquals('paid', $order->fresh()->status);
    }

    public function test_wrong_token_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Token' => 'wrong-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'rejected',
                 ]);

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_missing_token_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'rejected',
                 ]);

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_wrong_amount_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1000, // wrong amount
            'status'      => 'paid',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'rejected',
                     'message' => 'Invalid amount',
                 ]);

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_invalid_payment_status_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'failed', // invalid status
        ]);

        $response->assertStatus(422); // validation error
    }

    public function test_duplicate_event_id_different_payload_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'pending',
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        // First payment
        $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        // Second payment with same event_id but different amount
        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 999999,
            'status'      => 'paid',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'rejected',
                     'message' => 'Event ID reused with different payload',
                 ]);

        // Order status should remain paid (already paid by first identical event)
        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_payment_for_nonexistent_order_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> 'ORD-NONEXISTENT',
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'rejected',
                     'message' => 'Order not found',
                 ]);
    }

    public function test_payment_for_already_paid_order_rejected(): void
    {
        $product = Product::factory()->create([
            'price' => 1500000,
            'stock' => 1,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'checkout_price' => 1500000,
            'total'      => 1500000,
            'status'     => 'paid', // already paid
            'order_number' => 'ORD-20260924-ABC123',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Token' => 'local-test-token',
        ])->postJson('/api/mock-payments', [
            'event_id'    => 'evt-001',
            'order_number'=> $order->order_number,
            'amount'      => 1500000,
            'status'      => 'paid',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'rejected',
                 ]);
    }
}