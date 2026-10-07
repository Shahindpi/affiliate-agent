<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_sources', function (Blueprint $t) {
            $t->timestamp('last_tested_at')->nullable();
            $t->string('last_test_status')->nullable();
            $t->text('last_test_error')->nullable();
            $t->timestamp('sync_started_at')->nullable();
            $t->timestamp('last_sync_failed_at')->nullable();
            $t->unsignedSmallInteger('last_http_status')->nullable();
        });
        Schema::table('agent_source_runs', function (Blueprint $t) {
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->boolean('content_changed')->nullable();
            $t->unsignedInteger('extracted_items')->default(0);
            $t->json('metadata')->nullable();
        });
        DB::table('agent_sources')->where('status', 'ERROR')->update(['status' => 'SYNC_FAILED']);
    }
    public function down(): void
    {
        Schema::table('agent_source_runs', fn (Blueprint $t) => $t->dropColumn(['http_status', 'content_changed', 'extracted_items', 'metadata']));
        Schema::table('agent_sources', fn (Blueprint $t) => $t->dropColumn(['last_tested_at', 'last_test_status', 'last_test_error', 'sync_started_at', 'last_sync_failed_at', 'last_http_status']));
    }
};
