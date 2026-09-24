<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Mock payment processing. Shared by API endpoint.
 *
 * Transaction boundary: lock order row → re-check event idempotency inside
 * transaction → validate amount/status → insert payment event → mark order paid.
 *
 * Idempotency:
 * - same event_id + same payload  → already_processed (no-op)
 * - same event_id + different payload → 400 rejected (fingerprint mismatch)
 */
final class PaymentService
{
    public const RESPONSE_ACCEPTED = 'accepted';
    public const RESPONSE_ALREADY_PROCESSED = 'already_processed';
    public const RESPONSE_REJECTED = 'rejected';

    /**
     * @return array{status: string, message?: string}
     *
     * @throws HttpException 404 when order not found, 400 when rejected
     */
    public function process(string $eventId, string $orderNumber, int $amount, string $status): array
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        $payloadHash = $this->fingerprint($eventId, $orderNumber, $amount, $status);

        // Fast-path idempotency check (also re-checked inside transaction).
        if ($order->paymentEvents()->where('event_id', $eventId)->exists()) {
            $existing = $order->paymentEvents()->where('event_id', $eventId)->first();

            if ($existing->payload_hash !== $payloadHash) {
                throw new HttpException(400, 'Event ID reused with different payload');
            }

            return ['status' => self::RESPONSE_ALREADY_PROCESSED];
        }

        return DB::transaction(function () use ($eventId, $order, $amount, $status, $payloadHash, $orderNumber) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Re-check under lock: concurrent duplicate event with identical payload.
            $existing = $order->paymentEvents()->where('event_id', $eventId)->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw new HttpException(400, 'Event ID reused with different payload');
                }

                return ['status' => self::RESPONSE_ALREADY_PROCESSED];
            }

            if ($order->status === Order::STATUS_PAID) {
                throw new HttpException(400, 'Order already paid');
            }

            if ($order->total !== $amount) {
                throw new HttpException(400, 'Invalid amount');
            }

            if ($status !== 'paid') {
                throw new HttpException(400, 'Invalid payment status');
            }

            PaymentEvent::create([
                'event_id'     => $eventId,
                'order_id'     => $order->id,
                'amount'       => $amount,
                'status'       => $status,
                'payload_hash' => $payloadHash,
            ]);

            $order->update(['status' => Order::STATUS_PAID]);

            return ['status' => self::RESPONSE_ACCEPTED];
        });
    }

    /**
     * SHA-256 fingerprint of the normalized payment payload.
     */
    private function fingerprint(string $eventId, string $orderNumber, int $amount, string $status): string
    {
        return hash('sha256', json_encode([
            'event_id'     => $eventId,
            'order_number' => $orderNumber,
            'amount'       => $amount,
            'status'       => $status,
        ]));
    }
}