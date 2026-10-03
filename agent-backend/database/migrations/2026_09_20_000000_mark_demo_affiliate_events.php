<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('affiliate_events', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });
        // Prior demo seeders use this reserved deterministic session namespace.
        DB::table('affiliate_events')->where('session_id', 'like', '00000000-0000-4000-8000-%')->update(['is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('affiliate_events', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
