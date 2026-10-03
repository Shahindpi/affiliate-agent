<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('affiliate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_product_id')->constrained('affiliate_products')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('kind', 20);
            $table->uuid('session_id');
            $table->timestamps();
            $table->index(['affiliate_product_id', 'kind', 'created_at']);
            $table->index(['brand_id', 'kind', 'created_at']);
            $table->index(['affiliate_product_id', 'session_id', 'kind']);
        });
    }

    public function down(): void { Schema::dropIfExists('affiliate_events'); }
};
