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
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('tagline')->nullable()->after('slug');
            $table->string('logo_path')->nullable()->after('tagline');
            $table->string('accent_color', 7)->nullable()->after('logo_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('physical')->after('name');
            $table->string('brand')->nullable()->after('type');
            $table->string('category')->nullable()->after('brand');
            $table->string('image_path')->nullable()->after('description');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['type', 'brand', 'category', 'image_path']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['slug', 'tagline', 'logo_path', 'accent_color']);
        });
    }
};
