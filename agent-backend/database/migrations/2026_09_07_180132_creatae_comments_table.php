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
        Schema::create('comments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('post_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('comments')
                ->nullOnDelete();

            $table->string('name', 150);

            $table->string('email');

            $table->string('website')->nullable();

            $table->text('comment');

            $table->enum('status', [
                'pending',
                'approved',
                'spam',
                'rejected',
            ])->default('pending');

            $table->string('ip_address')->nullable();

            $table->text('user_agent')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};