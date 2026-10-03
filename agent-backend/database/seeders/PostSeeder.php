<?php

namespace Database\Seeders;

use App\Models\AffiliateProduct;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            'Choosing an AI writing companion', 'A developer toolkit for small teams',
            'Designing a more focused workflow', 'How to evaluate hosting features',
            'A guide to secure passwords', 'Comparing creative canvas tools',
            'Building a reliable research process', 'Productivity habits for distributed teams',
            'What to measure in marketing reports', 'Reviewing a modern task planner',
            'Getting started with cloud workspaces', 'A practical interface design checklist',
            'Draft: our next product comparison', 'Draft: upcoming security guide',
            'Draft: tips for productive team meetings',
        ];
        $types = ['article', 'tutorial', 'review', 'resource', 'news'];
        $categories = Category::orderBy('sort_order')->get();
        $tags = Tag::orderBy('id')->get();
        $products = AffiliateProduct::orderBy('id')->get();
        $author = User::where('email', 'admin@example.com')->firstOrFail();
        foreach ($titles as $index => $title) {
            $published = $index < 12;
            $post = Post::updateOrCreate(['slug' => Str::slug($title)], [
                'title' => $title, 'user_id' => $author->id,
                'category_id' => $categories[$index % $categories->count()]->id,
                'excerpt' => "A practical look at {$title} with useful considerations for readers.",
                'content' => "<h2>{$title}</h2><p>This guide explores the features that matter, how the product can fit everyday work, and the questions to ask before deciding.</p><ul><li>Assess the workflow.</li><li>Check the available features.</li><li>Compare alternatives carefully.</li></ul>",
                'featured_image' => 'uploads/demo/post-'.(($index % 3) + 1).'.png',
                'post_type' => $types[$index % count($types)] === 'resource' ? 'article' : $types[$index % count($types)],
                'status' => $published ? 'published' : 'draft',
                'published_at' => $published ? now()->subDays(12 - $index) : null,
                'views' => $published ? (12 - $index) * 11 : 0,
                'reading_time' => 4 + ($index % 4), 'allow_comments' => $published,
            ]);
            $post->tags()->sync([$tags[$index % $tags->count()]->id, $tags[($index + 2) % $tags->count()]->id]);
            $post->affiliateProducts()->sync([
                $products[$index % $products->count()]->id => ['sort_order' => 0, 'is_primary' => true],
            ]);
            if ($index < 3) $post->seoMeta()->updateOrCreate([], [
                'meta_title' => $title.' | Dewdora guide',
                'meta_description' => "Read Dewdora's guide to {$title}.",
            ]);
        }
    }
}
