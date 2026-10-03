<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('homepage_sections')->nullable();
        });
        Schema::table('affiliate_events', function (Blueprint $table) {
            $table->string('placement', 60)->nullable()->index();
            $table->foreignId('affiliate_network_id')->nullable()->constrained('affiliate_networks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_network_id');
            $table->dropColumn('placement');
        });
        Schema::table('site_settings', fn (Blueprint $table) => $table->dropColumn('homepage_sections'));
    }
};
