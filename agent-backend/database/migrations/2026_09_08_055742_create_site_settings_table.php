<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {

            $table->id();

            $table->string('site_name')->default('Dewdora');
            $table->string('site_tagline')->nullable();
            $table->string('site_url')->nullable();

            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();

            $table->string('default_meta_title')->nullable();
            $table->text('default_meta_description')->nullable();
            $table->text('default_meta_keywords')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('contact_address')->nullable();

            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('github')->nullable();
            $table->string('youtube')->nullable();
            $table->string('instagram')->nullable();

            $table->string('google_analytics_id')->nullable();
            $table->string('google_search_console_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};