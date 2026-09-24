<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function process(Request $request): JsonResponse
    {
        $token = $request->header('X-Payment-Token');

        if (!$token || $token !== config('app.payment_token')) {
            return response()->json([
                'status'  => PaymentService::RESPONSE_REJECTED,
                'message' => 'Invalid or missing payment token',
            ], 400);
        }

        $validated = $request->validate([
            'event_id'     => 'required|string|max:255',
            'order_number' => 'required|string|max:255',
            'amount'       => 'required|integer|min:0',
            'status'       => 'required|string|in:paid',
        ]);

        try {
            $result = $this->paymentService->process(
                $validated['event_id'],
                $validated['order_number'],
                (int) $validated['amount'],
                $validated['status'],
            );

            return response()->json($result);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => PaymentService::RESPONSE_REJECTED,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}