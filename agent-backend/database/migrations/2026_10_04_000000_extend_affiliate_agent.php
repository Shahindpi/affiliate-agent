<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_sources', function (Blueprint $t) {
            $t->id(); $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->restrictOnDelete();
            $t->string('name'); $t->string('type'); $t->text('url')->nullable();
            $t->text('credentials')->nullable(); $t->json('allowed_domains')->nullable();
            $t->text('notes')->nullable(); $t->boolean('enabled')->default(true);
            $t->unsignedInteger('priority')->default(50); $t->unsignedInteger('frequency_hours')->default(24);
            $t->string('status')->default('NOT_SYNCED'); $t->text('last_error')->nullable();
            $t->timestamp('last_synced_at')->nullable(); $t->timestamp('next_sync_at')->nullable(); $t->timestamps();
        });
        Schema::create('agent_source_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('source_id')->constrained('agent_sources')->restrictOnDelete();
            $t->string('status'); $t->text('error')->nullable(); $t->unsignedInteger('documents')->default(0);
            $t->timestamp('started_at'); $t->timestamp('finished_at')->nullable(); $t->timestamps();
        });
        Schema::create('agent_source_documents', function (Blueprint $t) {
            $t->id(); $t->foreignId('source_id')->constrained('agent_sources')->restrictOnDelete();
            $t->string('source_url', 2000)->nullable(); $t->string('title')->nullable();
            $t->longText('body'); $t->string('sha256', 64); $t->string('status')->default('PENDING');
            $t->timestamp('synced_at'); $t->timestamp('approved_at')->nullable(); $t->timestamps();
            $t->index(['source_id', 'status']);
        });
        Schema::create('agent_social_accounts', function (Blueprint $t) {
            $t->id(); $t->string('platform'); $t->string('external_id'); $t->string('name');
            $t->string('username')->nullable(); $t->text('access_token')->nullable(); $t->text('refresh_token')->nullable();
            $t->timestamp('expires_at')->nullable(); $t->json('scopes')->nullable(); $t->json('metadata')->nullable();
            $t->boolean('publishing_enabled')->default(false); $t->string('status')->default('CONNECTED');
            $t->string('api_review_status')->default('UNKNOWN'); $t->timestamp('last_verified_at')->nullable();
            $t->text('last_error')->nullable(); $t->timestamps(); $t->unique(['platform', 'external_id']);
        });
        Schema::create('agent_settings', function (Blueprint $t) {
            $t->id(); $t->string('timezone')->default('Asia/Dhaka'); $t->unsignedTinyInteger('videos_per_day')->default(2);
            $t->string('generation_time')->default('06:00'); $t->json('generation_days');
            $t->unsignedTinyInteger('horizon_days')->default(7); $t->unsignedTinyInteger('duration_seconds')->default(25);
            $t->json('mix'); $t->json('publishing_slots'); $t->json('enabled_brand_ids')->nullable();
            $t->boolean('generation_enabled')->default(false); $t->boolean('publishing_enabled')->default(false);
            $t->string('late_policy')->default('next_slot'); $t->timestamps();
        });
        Schema::table('agent_contents', function (Blueprint $t) {
            $t->string('content_type')->nullable()->index(); $t->date('planned_for')->nullable()->index();
        });
        Schema::table('agent_publications', function (Blueprint $t) {
            $t->foreignId('social_account_id')->nullable()->constrained('agent_social_accounts')->nullOnDelete();
            $t->timestamp('started_at')->nullable(); $t->unsignedInteger('retry_count')->default(0);
            $t->json('response_metadata')->nullable(); $t->string('platform_url', 2000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_publications', fn (Blueprint $t) => $t->dropConstrainedForeignId('social_account_id'));
        Schema::table('agent_publications', fn (Blueprint $t) => $t->dropColumn(['started_at', 'retry_count', 'response_metadata', 'platform_url']));
        Schema::table('agent_contents', fn (Blueprint $t) => $t->dropColumn(['content_type', 'planned_for']));
        foreach (['agent_settings', 'agent_social_accounts', 'agent_source_documents', 'agent_source_runs', 'agent_sources'] as $table) Schema::dropIfExists($table);
    }
};
