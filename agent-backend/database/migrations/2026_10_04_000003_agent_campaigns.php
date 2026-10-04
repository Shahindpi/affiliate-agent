<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('affiliate_product_id')->nullable()->constrained('affiliate_products')->restrictOnDelete();
            $t->string('name'); $t->text('brief'); $t->unsignedInteger('priority')->default(50);
            $t->unsignedInteger('monthly_target')->default(10); $t->date('starts_on')->nullable(); $t->date('ends_on')->nullable();
            $t->boolean('enabled')->default(true); $t->timestamps();
        });
        Schema::table('agent_contents', fn (Blueprint $t) => $t->foreignId('campaign_id')->nullable()->constrained('agent_campaigns')->nullOnDelete());
    }
    public function down(): void
    {
        Schema::table('agent_contents', fn (Blueprint $t) => $t->dropConstrainedForeignId('campaign_id'));
        Schema::dropIfExists('agent_campaigns');
    }
};
