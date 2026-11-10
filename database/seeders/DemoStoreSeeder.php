<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Seed a merchant with a small but realistic catalogue and private rules.
     */
    public function run(): void
    {
        $merchant = Merchant::updateOrCreate(
            ['email' => 'store@offerpilot.test'],
            [
                'name' => 'Northsound Audio',
                'slug' => 'northsound-audio',
                'tagline' => 'Studio-grade sound, sold by people who use it.',
            ],
        );

        $catalogue = [
            [
                'name' => 'Studio Headphones',
                'sku' => 'HEADPHONES-PRO',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'Closed-back headphones with a replaceable cable and replaceable earpads. Built for long mixing sessions.',
                'image_path' => 'demo/audio.svg',
                'list_price_cents' => 25_000,
                'floor_price_cents' => 21_000,
                'stock_quantity' => 5,
            ],
            [
                'name' => 'Wireless Earbuds',
                'sku' => 'EARBUDS-AIR',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'Compact earbuds with a pocket charging case and 24 hours of total playback.',
                'image_path' => 'demo/audio.svg',
                'list_price_cents' => 17_500,
                'floor_price_cents' => 14_500,
                'stock_quantity' => 8,
            ],
            [
                'name' => 'Desk Microphone',
                'sku' => 'MIC-DESK-01',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'Cardioid USB microphone for calls, streaming and voiceover. Shock mount included.',
                'image_path' => 'demo/audio.svg',
                'list_price_cents' => 12_000,
                'floor_price_cents' => 9_900,
                'stock_quantity' => 6,
            ],
            [
                'name' => '14" Ultrabook',
                'sku' => 'LAPTOP-14',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Computing',
                'description' => '1.2kg aluminium laptop, 16GB memory, 512GB storage. All-day battery for work on the move.',
                'image_path' => 'demo/computing.svg',
                'list_price_cents' => 149_900,
                'floor_price_cents' => 129_000,
                'stock_quantity' => 3,
            ],
            [
                'name' => '27" 4K Monitor',
                'sku' => 'MONITOR-4K',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Displays',
                'description' => 'Factory-calibrated 4K display with a single-cable USB-C connection and an aluminium stand.',
                'image_path' => 'demo/displays.svg',
                'list_price_cents' => 54_900,
                'floor_price_cents' => 47_500,
                'stock_quantity' => 4,
            ],
            [
                'name' => 'Mechanical Keyboard',
                'sku' => 'KEYBOARD-MECH',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Accessories',
                'description' => 'Hot-swappable mechanical keyboard with a machined aluminium case and PBT keycaps.',
                'image_path' => 'demo/accessories.svg',
                'list_price_cents' => 18_500,
                'floor_price_cents' => 15_000,
                'stock_quantity' => 10,
            ],
            [
                'name' => 'Studio Setup Consultation',
                'sku' => 'SERVICE-STUDIO-SETUP',
                'type' => 'service',
                'brand' => 'Northsound',
                'category' => 'Services',
                'description' => 'A 90-minute session to plan and tune your recording setup. Remote or in-studio.',
                'image_path' => 'demo/audio.svg',
                'list_price_cents' => 35_000,
                'floor_price_cents' => 28_000,
                'stock_quantity' => 0,
            ],
        ];

        foreach ($catalogue as $item) {
            $product = Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'merchant_id' => $merchant->id,
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'brand' => $item['brand'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'image_path' => $item['image_path'],
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
