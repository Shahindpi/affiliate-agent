<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();

            /*
             * Polymorphic relationship.
             *
             * Example:
             * seoable_type = App\Models\Post
             * seoable_id   = 1
             */
            $table->morphs('seoable');

            $table->string('meta_title', 255)->nullable();

            $table->text('meta_description')->nullable();

            $table->string('canonical_url')->nullable();

            $table->string('og_title', 255)->nullable();

            $table->text('og_description')->nullable();

            $table->string('og_image')->nullable();

            $table->string('twitter_title', 255)->nullable();

            $table->text('twitter_description')->nullable();

            $table->string('twitter_image')->nullable();

            $table->string('robots', 100)
                ->default('index,follow');

            $table->json('schema_data')->nullable();

            $table->timestamps();

            $table->unique([
                'seoable_type',
                'seoable_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};