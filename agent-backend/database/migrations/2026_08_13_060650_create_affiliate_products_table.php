<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_products', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('affiliate_network_id')
                ->nullable()
                ->constrained('affiliate_networks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('name', 200);

            $table->string('slug', 220);

            $table->text('short_description')->nullable();

            $table->longText('description')->nullable();

            $table->string('website_url')->nullable();

            /*
             * The actual affiliate destination URL.
             *
             * Example:
             * https://example.com/?ref=your-affiliate-id
             */
            $table->text('affiliate_url');

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            $table->decimal('price', 12, 2)->nullable();

            $table->string('currency', 3)->default('USD');

            /*
             * Store commission as a percentage.
             *
             * Example:
             * 30.00 = 30%
             */
            $table->decimal('commission_rate', 5, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Product Features
            |--------------------------------------------------------------------------
            */

            $table->boolean('free_trial')->default(false);

            $table->unsignedTinyInteger('rating')->nullable();

            $table->string('featured_image')->nullable();

            $table->json('pros')->nullable();

            $table->json('cons')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('featured')->default(false);

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->unique('slug');

            $table->index([
                'status',
                'featured',
            ]);

            $table->index('brand_id');

            $table->index('affiliate_network_id');

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_products');
    }
};