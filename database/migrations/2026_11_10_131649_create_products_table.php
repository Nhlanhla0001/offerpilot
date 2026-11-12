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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('physical');
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('features')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedInteger('list_price_cents');
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'list_price_cents']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
