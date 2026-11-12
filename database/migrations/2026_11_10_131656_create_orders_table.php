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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->nullable()->unique();
            $table->foreignId('quote_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('shipping_method', 32)->default('flat');
            $table->string('paypal_order_id')->unique();
            $table->string('paypal_capture_id')->nullable()->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->default('created');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->string('fulfilment_status', 20)->default('unfulfilled');
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable();
            $table->string('experiment_variant', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
