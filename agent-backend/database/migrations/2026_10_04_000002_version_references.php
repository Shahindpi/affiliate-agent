<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('agent_versions', fn (Blueprint $t) => $t->json('references')->nullable()); }
    public function down(): void { Schema::table('agent_versions', fn (Blueprint $t) => $t->dropColumn('references')); }
};
