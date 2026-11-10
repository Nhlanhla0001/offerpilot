<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Seed a merchant with three products and their private pricing rules.
     */
    public function run(): void
    {
        $merchant = Merchant::updateOrCreate(
            ['email' => 'store@offerpilot.test'],
            ['name' => 'Northsound Audio'],
        );

        $catalogue = [
            [
                'name' => 'Studio Headphones',
                'sku' => 'HEADPHONES-PRO',
                'description' => 'Closed-back studio headphones with a replaceable cable.',
                'list_price_cents' => 25_000,
                'floor_price_cents' => 21_000,
                'stock_quantity' => 5,
            ],
            [
                'name' => 'Wireless Earbuds',
                'sku' => 'EARBUDS-AIR',
                'description' => 'Compact earbuds with a pocket charging case.',
                'list_price_cents' => 17_500,
                'floor_price_cents' => 14_500,
                'stock_quantity' => 8,
            ],
            [
                'name' => 'Desk Microphone',
                'sku' => 'MIC-DESK-01',
                'description' => 'Cardioid USB microphone for calls and recording.',
                'list_price_cents' => 12_000,
                'floor_price_cents' => 9_900,
                'stock_quantity' => 6,
            ],
        ];

        foreach ($catalogue as $item) {
            $product = Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'merchant_id' => $merchant->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'list_price_cents' => $item['list_price_cents'],
                    'stock_quantity' => $item['stock_quantity'],
                ],
            );

            $product->rules()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'floor_price_cents' => $item['floor_price_cents'],
                    'max_rounds' => 3,
                    'allow_alternatives' => true,
                ],
            );
        }
    }
}
