<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('agent_settings', fn (Blueprint $t) => $t->json('enabled_campaign_ids')->nullable()); }
    public function down(): void { Schema::table('agent_settings', fn (Blueprint $t) => $t->dropColumn('enabled_campaign_ids')); }
};
