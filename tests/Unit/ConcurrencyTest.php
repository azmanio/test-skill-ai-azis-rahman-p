<?php

namespace Tests\Unit;

use App\Services\CheckoutService;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Concurrency test: stock = 1, two parallel checkout processes (qty = 1 each).
     *
     * Uses pcntl_fork so both checkouts run in separate PHP processes with
     * separate PostgreSQL connections — a real race on the same product row.
     * lockForUpdate() serializes them: first acquires the row lock, decrements,
     * commits; second blocks until commit, then sees stock = 0 → 409.
     *
     * Expected: 1 success, 1 rejection (409), stock = 0, orders = 1.
     */
    public function test_concurrent_checkout_one_succeeds_one_rejected(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available — cannot fork processes.');
        }

        $product = Product::create([
            'name' => 'Antam 1 gram',
            'price' => 1500000,
            'stock' => 1,
        ]);
        $productId = $product->id;

        $pid1 = pcntl_fork();
        if ($pid1 === 0) {
            DB::purge();
            exit($this->attemptCheckout($productId));
        }

        $pid2 = pcntl_fork();
        if ($pid2 === 0) {
            DB::purge();
            usleep(50000); // let child 1 acquire the row lock first
            exit($this->attemptCheckout($productId));
        }

        pcntl_waitpid($pid1, $status1);
        pcntl_waitpid($pid2, $status2);

        $exitCodes = [pcntl_wexitstatus($status1), pcntl_wexitstatus($status2)];
        sort($exitCodes);

        // 0 = success, 1 = rejected (409 insufficient stock)
        $this->assertEquals(
            [0, 1],
            $exitCodes,
            'Expected exactly 1 successful and 1 rejected checkout, got: ' . implode(',', $exitCodes)
        );

        $product = Product::find($productId);
        $this->assertEquals(0, $product->stock, 'Stock must be 0 — never negative');
        $this->assertEquals(
            1,
            Order::where('product_id', $productId)->count(),
            'Exactly one order must be created'
        );
        $this->assertEquals('pending', Order::where('product_id', $productId)->first()->status);
    }

    /**
     * Runs the real CheckoutService in a forked process.
     *
     * @return int exit code: 0 = success, 1 = rejected (409), 9 = unexpected
     */
    private function attemptCheckout(int $productId): int
    {
        try {
            (new CheckoutService())->checkout($productId, 1);

            return 0;
        } catch (HttpException $e) {
            return $e->getStatusCode() === 409 ? 1 : 9;
        } catch (\Throwable) {
            return 9;
        }
    }
}