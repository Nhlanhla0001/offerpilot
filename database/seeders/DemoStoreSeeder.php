<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Seed a merchant with a small but realistic catalogue and private rules.
     */
    public function run(): void
    {
        $ownerId = User::query()->orderBy('id')->value('id');

        // A shopper account with no shop, so both roles can be seen side by side.
        User::query()->firstOrCreate(
            ['email' => 'buyer@offerpilot.test'],
            ['name' => 'Sam Shopper', 'password' => 'password'],
        );

        $merchant = Merchant::updateOrCreate(
            ['email' => 'store@offerpilot.test'],
            [
                'name' => 'Northsound Audio',
                'slug' => 'northsound-audio',
                'tagline' => 'Studio-grade sound, sold by people who use it.',
                'user_id' => $ownerId,
            ],
        );

        $catalogue = [
            [
                'name' => 'Northsound SH-1 Studio Headphones',
                'sku' => 'HEADPHONES-PRO',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'Closed-back wireless headphones for long mixing and tracking sessions. The SH-1 pairs a controlled low end with a neutral midrange, so what you hear in the chair is what leaves the room. A detachable cable is included for wired monitoring, and the earpads can be replaced when they wear.',
                'features' => [
                    'Closed-back 45 mm drivers isolate the room without a muddy low end',
                    'Up to 40 hours of playback, with USB-C charging',
                    'Detachable 1.2 m cable for wired, zero-latency monitoring',
                    'Replaceable earpads and a padded steel headband',
                    'Bluetooth 5.3 with multipoint pairing for a laptop and phone',
                ],
                'specifications' => [
                    'Driver' => '45 mm dynamic, closed back',
                    'Frequency response' => '18 Hz – 20 kHz',
                    'Impedance' => '38 Ω',
                    'Battery' => 'Up to 40 hours',
                    'Charging' => 'USB-C',
                    'Wireless' => 'Bluetooth 5.3, multipoint',
                    'Cable' => '1.2 m detachable, 3.5 mm',
                    'Weight' => '285 g',
                    'In the box' => 'Headphones, USB-C cable, audio cable, carry pouch',
                ],
                'image_path' => 'demo/headphones.jpg',
                'list_price_cents' => 25_000,
                'floor_price_cents' => 21_000,
                'stock_quantity' => 5,
            ],
            [
                'name' => 'Northsound Air Compact Wireless Earbuds',
                'sku' => 'EARBUDS-AIR',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'Pocket-sized earbuds for calls, commutes and light listening. The case charges over USB-C and holds another three full charges, and the buds sit deep enough to stay put on a walk without sealing you off from traffic.',
                'features' => [
                    '24 hours of total playback with the pocket charging case',
                    'Bluetooth 5.3 with quick pairing and a stable 10 m range',
                    'Two microphones per bud for clearer calls',
                    'IPX4 splash resistance for rain and workouts',
                    'USB-C charging with a 10-minute quick charge for 2 hours of playback',
                ],
                'specifications' => [
                    'Driver' => '10 mm dynamic',
                    'Bluetooth' => '5.3',
                    'Battery' => '6 h per charge, 24 h with case',
                    'Charging' => 'USB-C, 10-minute quick charge',
                    'Water resistance' => 'IPX4',
                    'Weight' => '4.6 g per bud, 38 g case',
                    'In the box' => 'Earbuds, charging case, USB-C cable, three ear-tip sizes',
                ],
                'image_path' => 'demo/earbuds.jpg',
                'list_price_cents' => 17_500,
                'floor_price_cents' => 14_500,
                'stock_quantity' => 8,
            ],
            [
                'name' => 'Northsound DM-2 USB Desk Microphone',
                'sku' => 'MIC-DESK-01',
                'type' => 'physical',
                'brand' => 'Northsound',
                'category' => 'Audio',
                'description' => 'A cardioid USB microphone for calls, streaming and voiceover. It plugs straight into a laptop with no interface, and the included shock mount keeps keyboard thumps out of the recording. The headphone jack gives you zero-latency monitoring while you talk.',
                'features' => [
                    'Cardioid condenser capsule rejects room noise behind the mic',
                    'USB-C plug-and-play with no audio interface required',
                    'Zero-latency 3.5 mm headphone monitoring',
                    'Metal shock mount and desk stand included',
                    'Mute button with an LED ring that shows live status',
                ],
                'specifications' => [
                    'Capsule' => 'Cardioid condenser',
                    'Sample rate' => '24-bit / 96 kHz',
                    'Connection' => 'USB-C, detachable 1.5 m cable',
                    'Monitoring' => '3.5 mm headphone jack',
                    'Mount' => 'Metal shock mount with desk stand',
                    'Weight' => '540 g with mount',
                    'In the box' => 'Microphone, shock mount, desk stand, USB-C cable',
                ],
                'image_path' => 'demo/microphone.jpg',
                'list_price_cents' => 12_000,
                'floor_price_cents' => 9_900,
                'stock_quantity' => 6,
            ],
            [
                'name' => 'Aster 14 Ultrabook',
                'sku' => 'LAPTOP-14',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Computing',
                'description' => 'A 1.2 kg aluminium laptop built for work that moves. The 14-inch display is bright enough for a café table, the keyboard has real travel, and the battery lasts a full working day of documents, calls and browser tabs. Charging over USB-C means one cable for the laptop and the phone.',
                'features' => [
                    '14-inch 2.8K display with a matte anti-glare finish',
                    '16 GB memory and 512 GB solid-state storage',
                    'Up to 14 hours of light office use on one charge',
                    '1.2 kg aluminium chassis with a backlit keyboard',
                    'USB-C charging, two USB-C ports, one USB-A and a 3.5 mm jack',
                ],
                'specifications' => [
                    'Display' => '14-inch 2.8K (2880 × 1800) matte',
                    'Processor' => '8-core, 12 threads',
                    'Memory' => '16 GB LPDDR5',
                    'Storage' => '512 GB NVMe SSD',
                    'Battery' => '63 Wh, up to 14 hours',
                    'Weight' => '1.2 kg',
                    'Ports' => '2 × USB-C, 1 × USB-A, 3.5 mm, HDMI',
                    'In the box' => 'Laptop, 65 W USB-C charger, quick-start guide',
                ],
                'image_path' => 'demo/laptop.jpg',
                'list_price_cents' => 149_900,
                'floor_price_cents' => 129_000,
                'stock_quantity' => 3,
            ],
            [
                'name' => 'Aster 27-inch 4K Monitor',
                'sku' => 'MONITOR-4K',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Displays',
                'description' => 'A factory-calibrated 4K display with a single-cable USB-C connection. Text stays crisp at 100% scaling, the stand adjusts without tools, and the built-in hub charges a laptop at 90 W while carrying video and data. Made for a desk that has to do real work.',
                'features' => [
                    '27-inch 4K IPS panel, factory calibrated to sRGB',
                    'Single USB-C cable for video, data and 90 W charging',
                    'Height, tilt and pivot adjustment with a tool-free stand',
                    'Three-port USB hub and a 3.5 mm headphone output',
                    'Flicker-free backlight and a low blue-light mode',
                ],
                'specifications' => [
                    'Panel' => '27-inch IPS, 3840 × 2160',
                    'Refresh rate' => '60 Hz',
                    'Colour' => '99% sRGB, factory calibrated',
                    'Brightness' => '400 cd/m²',
                    'Ports' => 'USB-C (90 W), 2 × HDMI, 3 × USB-A, 3.5 mm',
                    'Stand' => 'Height, tilt, pivot, VESA 100 × 100',
                    'Weight' => '6.4 kg with stand',
                    'In the box' => 'Monitor, stand, USB-C cable, HDMI cable, power cable',
                ],
                'image_path' => 'demo/monitor.jpg',
                'list_price_cents' => 54_900,
                'floor_price_cents' => 47_500,
                'stock_quantity' => 4,
            ],
            [
                'name' => 'Aster K1 Mechanical Keyboard',
                'sku' => 'KEYBOARD-MECH',
                'type' => 'physical',
                'brand' => 'Aster',
                'category' => 'Accessories',
                'description' => 'A compact mechanical keyboard with a machined aluminium case and dye-sublimated PBT keycaps. The hot-swap sockets let you change switches without a soldering iron, and the gasket mount softens the bottom-out for long typing days. Wired USB-C, with no software required.',
                'features' => [
                    '75% layout with dedicated arrow keys and a volume knob',
                    'Hot-swappable sockets for 3- and 5-pin switches',
                    'Machined aluminium case with a gasket mount',
                    'Dye-sublimated PBT keycaps that will not shine quickly',
                    'USB-C wired connection with a detachable braided cable',
                ],
                'specifications' => [
                    'Layout' => '75%, 82 keys',
                    'Switches' => 'Tactile brown, hot-swappable',
                    'Keycaps' => 'Dye-sublimated PBT',
                    'Case' => 'Machined aluminium',
                    'Connection' => 'USB-C, detachable cable',
                    'Weight' => '980 g',
                    'In the box' => 'Keyboard, USB-C cable, keycap puller, switch puller',
                ],
                'image_path' => 'demo/keyboard.jpg',
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
                'description' => 'A 90-minute session with an engineer to plan or tune a recording setup. Bring your room, your gear list and the sound you are chasing; you leave with a signal chain, a placement plan and a short list of what to change first. Available remotely or in our studio.',
                'features' => [
                    '90 minutes with a working recording engineer',
                    'Room, monitoring and microphone placement review',
                    'A written signal-chain plan you can keep',
                    'Remote over video call, or in the Northsound studio',
                    'Follow-up notes within two working days',
                ],
                'specifications' => [
                    'Duration' => '90 minutes',
                    'Format' => 'Remote video call or in-studio',
                    'Includes' => 'Written setup plan and follow-up notes',
                    'Rescheduling' => 'Free up to 24 hours before the session',
                    'Best for' => 'Home studios, podcast setups, voiceover rooms',
                ],
                'image_path' => 'demo/studio.jpg',
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
                    'features' => $item['features'],
                    'specifications' => $item['specifications'],
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
