<?php

namespace Database\Seeders;

use App\Models\AffiliateNetwork;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroBanner;
use App\Models\Post;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Only used by the isolated browser acceptance database. */
class RuntimeAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Runtime Admin', 'username' => 'runtime-admin',
            'email' => 'runtime@example.test', 'password' => 'RuntimeTest!2026',
            'role_id' => Role::where('slug', 'admin')->firstOrFail()->id, 'status' => true,
        ]);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand', 'status' => true]);
        $category = Category::create(['name' => 'Test Tools', 'slug' => 'test-tools', 'status' => true]);
        $network = AffiliateNetwork::create(['name' => 'Test Network', 'slug' => 'test-network', 'status' => true]);
        for ($id = 1; $id <= 8; $id++) {
            AffiliateProduct::create([
                'name' => "Test Product {$id}", 'slug' => "test-product-{$id}",
                'short_description' => "Practical product {$id} for everyday work.",
                'featured_image' => 'uploads/images/test-product.png',
                'affiliate_url' => "https://example.com/offer/{$id}",
                'brand_id' => $brand->id, 'category_id' => $category->id,
                'affiliate_network_id' => $network->id, 'status' => true,
            ]);
        }
        Post::create([
            'title' => 'Test Review', 'slug' => 'test-review', 'excerpt' => 'A practical review.',
            'content' => '<p>A practical review.</p>', 'user_id' => $admin->id,
            'category_id' => $category->id, 'status' => 'published', 'published_at' => now(),
        ]);
        HeroBanner::create([
            'heading' => 'Test hero banner', 'description' => 'Explore practical products.',
            'cta_text' => 'Explore products', 'cta_url' => '/products', 'enabled' => true,
            'sort_order' => 0,
        ]);
        SiteSetting::updateOrCreate(['id' => 1], ['site_name' => 'Dewdora', 'google_analytics_id' => 'G-TEST12345']);
    }
}
