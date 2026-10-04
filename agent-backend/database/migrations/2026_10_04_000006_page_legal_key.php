<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('pages', fn (Blueprint $t) => $t->string('legal_key')->nullable()->unique()); }
    public function down(): void { Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('legal_key')); }
};
