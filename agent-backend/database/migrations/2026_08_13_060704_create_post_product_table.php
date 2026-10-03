<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_product', function (Blueprint $table) {
            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('affiliate_product_id')
                ->constrained('affiliate_products')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            /*
             * Controls product ordering inside an article.
             *
             * Example:
             * 1 = Cursor
             * 2 = GitHub Copilot
             * 3 = Claude
             */
            $table->unsignedInteger('sort_order')->default(0);

            /*
             * Allows us to mark a product as the primary
             * recommendation for a particular post.
             */
            $table->boolean('is_primary')->default(false);

            $table->primary([
                'post_id',
                'affiliate_product_id',
            ]);

            $table->index([
                'post_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_product');
    }
};