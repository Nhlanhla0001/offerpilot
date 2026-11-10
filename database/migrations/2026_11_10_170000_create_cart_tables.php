<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();

            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku', 64);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 32)->nullable()->unique()->after('id');
            $table->string('customer_name')->nullable()->after('quote_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->json('shipping_address')->nullable()->after('customer_email');
            $table->string('shipping_method', 32)->default('flat')->after('shipping_address');
            $table->unsignedInteger('subtotal_cents')->default(0)->after('currency');
            $table->unsignedInteger('shipping_cents')->default(0)->after('subtotal_cents');
            $table->string('fulfilment_status', 20)->default('unfulfilled')->after('status');
            $table->string('courier')->nullable()->after('fulfilment_status');
            $table->string('tracking_number')->nullable()->after('courier');
            $table->foreignId('merchant_id')->nullable()->after('order_number')->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->after('merchant_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cart_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn([
                'order_number', 'customer_name', 'customer_email', 'shipping_address',
                'shipping_method', 'subtotal_cents', 'shipping_cents', 'fulfilment_status',
                'courier', 'tracking_number',
            ]);
        });

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
