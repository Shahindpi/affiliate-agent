<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin', 'status' => true]);
        $user = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => 'Password123!', 'role_id' => $role->id, 'status' => true]);
        $this->withHeader('Origin', 'http://localhost:3000');
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])->assertOk();
        $this->withToken($response->json('data.token'));

        return $user;
    }

    public static function resources(): array
    {
        return array_map(fn ($name) => [$name], ['categories', 'tags', 'brands', 'affiliate-networks', 'roles', 'affiliate-products']);
    }

    #[DataProvider('resources')]
    public function test_resource_crud_and_public_contract(string $resource): void
    {
        $this->login();
        $url = '/api/v1/admin/'.$resource;
        $payload = ['name' => 'Test resource', 'slug' => 'test-resource', 'status' => true];
        if ($resource === 'affiliate-products') {
            $payload['affiliate_url'] = 'https://example.com/offer';
            $payload['featured'] = true;
        }
        $id = $this->postJson($url, $payload)->assertCreated()->json('data.id');
        $this->getJson($url.'?search=Test&per_page=1')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonPath('data.slug', 'test-resource');
        $this->putJson($url.'/'.$id, [...$payload, 'name' => 'Updated resource'])->assertOk()->assertJsonPath('data.name', 'Updated resource');
        if ($resource !== 'roles') {
            $publicName = $resource === 'affiliate-products' ? 'products' : $resource;
            $this->getJson('/api/v1/public/'.$publicName.'/test-resource')->assertOk();
        }
        $this->deleteJson($url.'/'.$id)->assertOk();
        $this->getJson($url.'/'.$id)->assertNotFound();
        if ($resource !== 'roles') {
            unset($payload['slug']);
            $payload['name'] = 'Automatic slug';
            $this->postJson($url, $payload)->assertCreated();
            $this->postJson($url, $payload)->assertUnprocessable();
        }
    }

    public function test_user_lifecycle_and_account_protections(): void
    {
        $admin = $this->login();
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'status' => true]);
        $payload = ['name' => 'Editor', 'username' => 'editor', 'email' => 'editor@example.test', 'role_id' => $role->id, 'status' => true, 'password' => 'Password123!', 'password_confirmation' => 'Password123!'];
        $id = $this->postJson('/api/v1/admin/users', $payload)->assertCreated()->json('data.id');
        $this->assertTrue(Hash::check('Password123!', User::findOrFail($id)->password));
        $this->getJson('/api/v1/admin/users?search=editor')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/admin/users/'.$id)->assertOk()->assertJsonMissingPath('data.password');
        $this->putJson('/api/v1/admin/users/'.$id, [...$payload, 'name' => 'Updated Editor'])->assertOk()->assertJsonPath('data.name', 'Updated Editor');
        $this->postJson('/api/v1/admin/users', $payload)->assertUnprocessable()->assertJsonValidationErrors(['email', 'username']);
        $this->deleteJson('/api/v1/admin/users/'.$admin->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/roles/'.$role->id)->assertUnprocessable();
        $this->putJson('/api/v1/admin/users/'.$id.'/password', ['password' => 'Changed123!', 'password_confirmation' => 'Changed123!'])->assertOk();
        $this->assertTrue(Hash::check('Changed123!', User::findOrFail($id)->password));
        $this->deleteJson('/api/v1/admin/users/'.$id)->assertOk();
        $this->deleteJson('/api/v1/admin/roles/'.$role->id)->assertOk();
    }

    public function test_dashboard_analytics_supports_test_database(): void
    {
        $this->login();
        $this->getJson('/api/v1/admin/dashboard/analytics')->assertOk()->assertJsonCount(12, 'data.posts_per_month');
    }

    public function test_inactive_role_returns_validation_error_instead_of_not_found(): void
    {
        $this->login();
        $role = Role::create(['name' => 'Inactive', 'slug' => 'inactive', 'status' => false]);
        $this->postJson('/api/v1/admin/users', ['name' => 'User', 'username' => 'user', 'email' => 'user@example.test', 'role_id' => $role->id, 'status' => true, 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('role_id');
    }

    public function test_homepage_shows_active_products_even_without_featured_flag(): void
    {
        $this->login();
        $this->postJson('/api/v1/admin/affiliate-products', [
            'name' => 'New product',
            'affiliate_url' => 'https://example.com/offer',
            'status' => true,
            'featured' => false,
        ])->assertCreated();
        $this->getJson('/api/v1/public/homepage')->assertOk()
            ->assertJsonPath('data.hero_products.0.name', 'New product');
    }

    public function test_post_publication_relationships_seo_and_cache_invalidation(): void
    {
        $this->login();
        $category = $this->postJson('/api/v1/admin/categories', ['name' => 'Tools', 'slug' => 'tools', 'status' => true])->assertCreated()->json('data.id');
        $product = $this->postJson('/api/v1/admin/affiliate-products', ['name' => 'Tool', 'slug' => 'tool', 'affiliate_url' => 'https://example.com/offer', 'category_id' => $category, 'status' => true])->assertCreated()->json('data.id');
        $payload = ['title' => 'Tool review', 'slug' => 'tool-review', 'content' => '<h2>Review</h2><p>Useful details.</p>', 'status' => 'published', 'post_type' => 'review', 'category_id' => $category];
        $id = $this->postJson('/api/v1/admin/posts', $payload)->assertCreated()->json('data.id');
        $this->putJson('/api/v1/admin/posts/'.$id.'/tags', ['tag_ids' => []])->assertOk();
        $this->putJson('/api/v1/admin/posts/'.$id.'/affiliate-products', ['products' => [['affiliate_product_id' => $product, 'sort_order' => 0, 'is_primary' => true]]])->assertOk();
        $this->getJson('/api/v1/public/posts/tool-review')->assertOk()->assertJsonPath('data.post.affiliate_products.0.affiliate_url', 'https://example.com/offer');
        $this->getJson('/api/v1/public/categories/tools')->assertOk()->assertJsonPath('data.posts.data.0.id', $id)->assertJsonPath('data.products.data.0.id', $product);
        $this->putJson('/api/v1/admin/posts/'.$id.'/seo', ['meta_title' => 'Custom review title'])->assertOk();
        $this->getJson('/api/v1/public/posts/tool-review')->assertOk()->assertJsonPath('data.post.seo.meta_title', 'Custom review title');
        $this->putJson('/api/v1/admin/posts/'.$id.'/affiliate-products', ['products' => []])->assertOk();
        $this->putJson('/api/v1/admin/posts/'.$id, [...$payload, 'status' => 'draft'])->assertOk();
        $this->getJson('/api/v1/public/posts/tool-review')->assertNotFound();
        $this->getJson('/api/v1/public/search?q=review')->assertOk();
        $this->deleteJson('/api/v1/admin/posts/'.$id)->assertOk();
    }

    public function test_media_usage_protection_and_inbox_flows(): void
    {
        Storage::fake('public');
        $this->login();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        $upload = $this->postJson('/api/v1/admin/media/upload', [
            'image' => UploadedFile::fake()->createWithContent('test.png', $png),
        ])->assertCreated();
        $path = $upload->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->getJson('/api/v1/admin/media')->assertOk();
        $product = $this->postJson('/api/v1/admin/affiliate-products', [
            'name' => 'Media product', 'affiliate_url' => 'https://example.com', 'status' => true, 'featured_image' => $path,
        ])->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/admin/media/delete', ['path' => $path])->assertUnprocessable()->assertJsonPath('errors.used_by.0', 'Affiliate Product: Media product');
        $this->deleteJson('/api/v1/admin/affiliate-products/'.$product)->assertOk();
        $this->deleteJson('/api/v1/admin/media/delete', ['path' => $path])->assertOk();
        Storage::disk('public')->assertMissing($path);

        $this->postJson('/api/v1/public/contact', ['name' => 'Visitor', 'email' => 'visitor@example.test', 'subject' => 'Hello', 'message' => 'A question about products.'])->assertCreated();
        $this->getJson('/api/v1/admin/contact/messages')->assertOk()->assertJsonPath('data.0.subject', 'Hello');
        $this->postJson('/api/v1/public/newsletter/subscribe', ['email' => 'subscriber@example.test'])->assertCreated();
        $this->getJson('/api/v1/admin/newsletter/subscribers')->assertOk()->assertJsonPath('data.0.email', 'subscriber@example.test');
        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->getJson('/api/v1/public/settings')->assertOk();
        $this->getJson('/api/v1/admin/profile')->assertOk();
    }

    public function test_admin_endpoints_require_an_active_administrator(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'status' => true]);
        $user = User::create(['name' => 'Editor', 'username' => 'editor', 'email' => 'editor@example.test', 'password' => 'Password123!', 'role_id' => $role->id, 'status' => true]);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/admin/users')->assertForbidden();
    }
    public function test_homepage_carousel_banner_and_all_page_sizes(): void
    {
        $this->login();
        $first = $this->postJson('/api/v1/admin/affiliate-products', ['name' => 'First', 'affiliate_url' => 'https://example.com/first', 'status' => true])->assertCreated()->json('data.id');
        $last = $this->postJson('/api/v1/admin/affiliate-products', ['name' => 'Last', 'affiliate_url' => 'https://example.com/last', 'status' => true])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/admin/affiliate-products?per_page=all')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/public/homepage')->assertOk()->assertJsonPath('data.carousel_products.0.id', $last)->assertJsonPath('data.carousel_products.1.id', $first);
        $payload = ['heading' => 'Autumn picks', 'description' => 'Shop our picks', 'background_image' => 'uploads/images/hero.png', 'cta_text' => 'Explore', 'cta_url' => '/products', 'enabled' => true, 'sort_order' => 2];
        $id = $this->postJson('/api/v1/admin/hero-banners', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/public/homepage')->assertOk()->assertJsonPath('data.hero_banners.0.heading', 'Autumn picks');
        $this->getJson('/api/v1/admin/hero-banners?per_page=all')->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson('/api/v1/admin/hero-banners/'.$id, [...$payload, 'enabled' => false])->assertOk();
        $this->getJson('/api/v1/public/homepage')->assertOk()->assertJsonCount(0, 'data.hero_banners');
        $this->deleteJson('/api/v1/admin/hero-banners/'.$id)->assertOk();
    }

    public function test_public_analytics_setting_is_configuration_only(): void
    {
        $this->login();
        $this->putJson('/api/v1/admin/settings', ['site_name' => 'Dewdora', 'google_analytics_id' => 'G-TEST12345'])->assertOk();
        $this->getJson('/api/v1/public/settings')->assertOk()->assertJsonPath('data.google_analytics_id', 'G-TEST12345');
        $this->putJson('/api/v1/admin/settings', ['site_name' => 'Dewdora', 'google_analytics_id' => 'not-a-measurement-id'])->assertUnprocessable();
    }

}
