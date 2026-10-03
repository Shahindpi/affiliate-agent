<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('title', 255);

            $table->string('slug', 280);

            $table->text('excerpt')->nullable();

            $table->longText('content');

            $table->string('featured_image')->nullable();

            $table->string('post_type', 30)
                ->default(PostType::ARTICLE->value);

            $table->string('status', 30)
                ->default(PostStatus::DRAFT->value);

            $table->timestamp('published_at')->nullable();

            $table->unsignedBigInteger('views')->default(0);

            $table->unsignedSmallInteger('reading_time')->default(1);

            $table->boolean('allow_comments')->default(false);

            $table->timestamps();

            $table->softDeletes();

            $table->unique('slug');

            $table->index(['status', 'published_at']);

            $table->index(['post_type', 'status']);

            $table->index('category_id');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};