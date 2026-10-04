<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_contents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $t->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->restrictOnDelete();
            $t->string('title');
            $t->string('status')->default('REVIEW_PENDING')->index();
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->unsignedBigInteger('final_approved_version_id')->nullable();
            $t->json('locks');
            $t->json('checkpoint')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('agent_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->constrained('agent_contents')->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->unsignedBigInteger('parent_version_id')->nullable();
            $t->unsignedBigInteger('restored_from_id')->nullable();
            $t->json('snapshot');
            $t->json('artifacts');
            $t->json('changes');
            $t->json('steps');
            $t->json('qa');
            $t->string('snapshot_hash', 64);
            $t->boolean('mock')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['content_id', 'number']);
        });
        Schema::table('agent_contents', function (Blueprint $t) {
            $t->foreign('current_version_id')->references('id')->on('agent_versions')->restrictOnDelete();
            $t->foreign('final_approved_version_id')->references('id')->on('agent_versions')->restrictOnDelete();
        });
        Schema::create('agent_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->constrained('agent_contents')->restrictOnDelete();
            $t->foreignId('base_version_id')->constrained('agent_versions')->restrictOnDelete();
            $t->unsignedBigInteger('result_version_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('feedback');
            $t->string('target')->default('all');
            $t->json('locks');
            $t->json('checkpoint')->nullable();
            $t->json('patch')->nullable();
            $t->string('status')->default('QUEUED');
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('agent_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->constrained('agent_contents')->restrictOnDelete();
            $t->foreignId('version_id')->constrained('agent_versions')->restrictOnDelete();
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->string('snapshot_hash', 64);
            $t->timestamp('approved_at');
            $t->timestamp('invalidated_at')->nullable();
        });
        Schema::create('agent_preferences', function (Blueprint $t) {
            $t->id();
            $t->string('scope')->default('global');
            $t->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $t->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->restrictOnDelete();
            $t->string('component')->default('all');
            $t->text('instruction');
            $t->boolean('enabled')->default(true);
            $t->foreignId('source_feedback_id')->nullable()->constrained('agent_feedback')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('agent_publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->constrained('agent_contents')->restrictOnDelete();
            $t->foreignId('version_id')->constrained('agent_versions')->restrictOnDelete();
            $t->string('snapshot_hash', 64);
            $t->string('platform');
            $t->string('mode')->default('manual');
            $t->string('status')->default('SCHEDULED')->index();
            $t->timestamp('scheduled_at');
            $t->timestamp('published_at')->nullable();
            $t->string('external_id')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['version_id', 'platform']);
        });
        Schema::create('agent_usage', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->nullable()->constrained('agent_contents')->restrictOnDelete();
            $t->string('provider');
            $t->string('operation');
            $t->string('model')->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('characters')->default(0);
            $t->decimal('estimated_cost', 12, 6)->nullable();
            $t->decimal('duration_seconds', 10, 3)->nullable();
            $t->boolean('mock')->default(false);
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['agent_usage', 'agent_publications', 'agent_preferences', 'agent_approvals', 'agent_feedback'] as $table) Schema::dropIfExists($table);
        Schema::table('agent_contents', function (Blueprint $t) {
            $t->dropForeign(['current_version_id']);
            $t->dropForeign(['final_approved_version_id']);
        });
        Schema::dropIfExists('agent_versions');
        Schema::dropIfExists('agent_contents');
    }
};
