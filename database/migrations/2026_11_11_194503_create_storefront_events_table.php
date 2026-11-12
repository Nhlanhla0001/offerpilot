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
        Schema::create('storefront_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('variant', 20)->nullable();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_class', 20)->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefront_events');
    }
};
