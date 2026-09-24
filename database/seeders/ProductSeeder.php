<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use updateOrCreate for each product to ensure idempotency
        Product::updateOrCreate(
            ['name' => 'Antam 1 gram'],
            ['price' => 1500000, 'stock' => 1]
        );

        Product::updateOrCreate(
            ['name' => 'UBS 1 gram'],
            ['price' => 1450000, 'stock' => 3]
        );

        Product::updateOrCreate(
            ['name' => 'Emasku 0.5 gram'],
            ['price' => 750000, 'stock' => 0]
        );
    }
}