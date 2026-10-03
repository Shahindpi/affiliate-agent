<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $posts = Post::published()->orderBy('id')->take(6)->get();
        foreach ($posts as $index => $post) {
            Comment::firstOrCreate(['post_id' => $post->id, 'email' => "demo-comment-{$index}@example.test"], [
                'name' => 'Demo Reader', 'comment' => 'A useful guide. I would like to compare more options.',
                'status' => ['approved', 'pending', 'rejected', 'spam'][$index % 4],
                'approved_at' => $index % 4 === 0 ? now() : null,
            ]);
        }
    }
}
