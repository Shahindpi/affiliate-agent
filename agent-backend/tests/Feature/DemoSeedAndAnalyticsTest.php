<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\AffiliateProduct;
use App\Models\AffiliateNetwork;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Comment;
use App\Models\HeroBanner;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeedAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_demo_database_and_first_party_events(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Admin@1234567', $admin->password));
        $this->assertSame('admin', $admin->role->slug);
        $this->assertTrue($admin->status);
        $this->assertSame(4, Role::count());
        $this->assertSame(4, User::count());
        $this->assertSame(8, Category::count());
        $this->assertSame(15, Tag::count());
        $this->assertSame(8, Brand::count());
        $this->assertSame(5, AffiliateNetwork::count());
        $this->assertSame(6, Comment::count());
        $this->assertSame(5, NewsletterSubscriber::count());
        $this->assertSame(1, SiteSetting::count());
        $this->assertGreaterThan(80, AffiliateEvent::count());
        $this->assertGreaterThanOrEqual(16, AffiliateProduct::where('status', true)->count());
        $this->assertGreaterThanOrEqual(12, Post::published()->count());
        $this->assertSame(3, HeroBanner::count());
        $this->assertTrue(Storage::disk('public')->exists('uploads/demo/product-1.png'));
        $this->seed();
        $this->assertSame(16, AffiliateProduct::where('status', true)->count());
        $this->assertSame(15, Post::count());
        $this->assertSame(3, HeroBanner::count());
        $this->getJson('/api/v1/public/homepage')->assertOk()
            ->assertJsonCount(12, 'data.carousel_products')
            ->assertJsonCount(12, 'data.latest_products')
            ->assertJsonCount(16, 'data.popular_products')
            ->assertJsonPath('data.homepage_sections.buying_guides', true)
            ->assertJsonPath('data.hero_banners.0.heading', 'Find your next useful tool');
        $homepage = $this->getJson('/api/v1/public/homepage')->json('data');
        $this->assertSame($homepage['latest_products'][0]['id'], $homepage['popular_products'][0]['id']);
        $firstPage = $this->getJson('/api/v1/public/products?per_page=3&page=5')->assertOk()->assertJsonCount(3, 'data')->json('data');
        $secondPage = $this->getJson('/api/v1/public/products?per_page=3&page=6')->assertOk()->assertJsonCount(1, 'data')->json('data');
        $this->assertNotContains($firstPage[0]['id'], array_column($homepage['latest_products'], 'id'));
        $this->assertNotSame($firstPage[2]['id'], $secondPage[0]['id']);
        $product = AffiliateProduct::orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $this->getJson('/api/v1/public/products/'.$product->slug)->assertOk()->assertJsonPath('data.product.brand.id', $product->brand_id);
        $post = Post::published()->firstOrFail();
        $this->getJson('/api/v1/public/posts/'.$post->slug)->assertOk()->assertJsonPath('data.post.id', $post->id);
        $session = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
        $payload = ['affiliate_product_id' => $product->id, 'session_id' => $session];
        $this->postJson('/api/v1/public/affiliate-events', $payload + ['kind' => 'impression'])->assertCreated()->assertJsonPath('data.recorded', true);
        $this->postJson('/api/v1/public/affiliate-events', $payload + ['kind' => 'impression'])->assertOk()->assertJsonPath('data.recorded', false);
        $this->postJson('/api/v1/public/affiliate-events', $payload + ['kind' => 'click', 'placement' => 'homepage_popular'])->assertCreated();
        $this->assertSame(1, AffiliateEvent::where('session_id', $session)->where('kind', 'impression')->count());
        $this->assertSame($product->brand_id, AffiliateEvent::where('session_id', $session)->firstOrFail()->brand_id);
        $this->assertSame('homepage_popular', AffiliateEvent::where('session_id', $session)->where('kind', 'click')->firstOrFail()->placement);
        $this->assertSame($product->affiliate_network_id, AffiliateEvent::where('session_id', $session)->firstOrFail()->affiliate_network_id);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'Admin@1234567'])->assertOk()->json('data.token');
        $this->assertNotEmpty($token);
        foreach (['users', 'roles', 'posts', 'affiliate-products', 'categories', 'tags', 'brands', 'affiliate-networks', 'hero-banners', 'media', 'dashboard'] as $resource) {
            $this->withToken($token)->getJson('/api/v1/admin/'.$resource)->assertOk();
        }
        foreach (['posts', 'products', 'categories', 'tags', 'brands', 'affiliate-networks', 'homepage'] as $resource) {
            $this->getJson('/api/v1/public/'.$resource)->assertOk();
        }
        $homepageSections = array_fill_keys(array_keys(\App\Support\HomepageSections::DEFAULTS), true);
        $homepageSections['buying_guides'] = false;
        $this->withToken($token)->putJson('/api/v1/admin/settings/homepage', ['homepage_sections' => $homepageSections])
            ->assertOk()->assertJsonPath('data.homepage_sections.buying_guides', false);
        $this->getJson('/api/v1/public/homepage')->assertJsonPath('data.homepage_sections.buying_guides', false);
        $homepageSections['buying_guides'] = true;
        $this->withToken($token)->putJson('/api/v1/admin/settings/homepage', ['homepage_sections' => $homepageSections])->assertOk();
        $this->getJson('/api/v1/public/homepage')->assertJsonPath('data.homepage_sections.buying_guides', true);
        $analytics = $this->withToken($token)->getJson('/api/v1/admin/affiliate-analytics')->assertOk()
            ->assertJsonStructure(['data' => ['products', 'brands', 'networks', 'placements', 'daily', 'totals']]);
        $analytics->assertJsonPath('data.source', 'first_party')->assertJsonPath('data.totals.impressions', 1)->assertJsonPath('data.totals.clicks', 1);
        $this->withToken($token)->getJson('/api/v1/admin/affiliate-analytics?demo=1')->assertOk()->assertJsonPath('data.source', 'demo')->assertJsonPath('data.totals.impressions', 48);
        $this->assertContains($product->id, array_column($analytics->json('data.products'), 'id'));
    }
}
