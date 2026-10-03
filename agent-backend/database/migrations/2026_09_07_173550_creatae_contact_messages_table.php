<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run migrations.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {

            $table->id();

            $table->string('name', 150);

            $table->string('email');

            $table->string('subject', 200);

            $table->longText('message');

            $table->enum('status', [
                'new',
                'read',
                'archived',
            ])->default('new');

            $table->string('ip_address')->nullable();

            $table->text('user_agent')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};