<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 255);

            $table->string('file_name', 255);

            $table->string('disk', 50)
                ->default('public');

            $table->string('path', 500);

            $table->string('mime_type', 100)
                ->nullable();

            $table->unsignedBigInteger('size')
                ->nullable();

            $table->unsignedInteger('width')
                ->nullable();

            $table->unsignedInteger('height')
                ->nullable();

            $table->string('alt_text', 255)
                ->nullable();

            $table->text('caption')
                ->nullable();

            $table->string('folder', 255)
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index('mime_type');

            $table->index('folder');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};