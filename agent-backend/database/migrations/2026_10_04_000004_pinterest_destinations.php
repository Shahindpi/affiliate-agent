<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL may leave the table behind when adding an index fails after CREATE TABLE.
        if (!Schema::hasTable('agent_pinterest_destinations')) {
            Schema::create('agent_pinterest_destinations', function (Blueprint $t) {
                $t->id(); $t->foreignId('social_account_id')->constrained('agent_social_accounts')->restrictOnDelete();
                $t->string('scope_type'); $t->unsignedBigInteger('scope_id')->default(0);
                $t->string('external_board_id'); $t->string('external_board_name');
                $t->string('external_section_id')->nullable(); $t->string('external_section_name')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasIndex('agent_pinterest_destinations', 'agent_pin_dest_scope_uq')) {
            Schema::table('agent_pinterest_destinations', function (Blueprint $t) {
                $t->unique(['social_account_id', 'scope_type', 'scope_id'], 'agent_pin_dest_scope_uq');
            });
        }
    }
    public function down(): void { Schema::dropIfExists('agent_pinterest_destinations'); }
};
