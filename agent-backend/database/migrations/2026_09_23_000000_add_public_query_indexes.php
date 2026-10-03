<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('affiliate_products', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'id'], 'products_public_latest_idx');
            $table->index(['status', 'category_id', 'featured', 'created_at'], 'products_category_listing_idx');
            $table->index(['status', 'brand_id', 'created_at'], 'products_brand_listing_idx');
        });
        Schema::table('affiliate_events', function (Blueprint $table) {
            $table->index(['affiliate_product_id', 'is_demo', 'kind'], 'events_product_demo_kind_idx');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['status', 'views', 'published_at'], 'posts_public_popular_idx');
            $table->index(['category_id', 'status', 'published_at'], 'posts_category_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_products', function (Blueprint $table) {
            $table->dropIndex('products_public_latest_idx');
            $table->dropIndex('products_category_listing_idx');
            $table->dropIndex('products_brand_listing_idx');
        });
        Schema::table('affiliate_events', fn (Blueprint $table) => $table->dropIndex('events_product_demo_kind_idx'));
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_public_popular_idx');
            $table->dropIndex('posts_category_listing_idx');
        });
    }
};
