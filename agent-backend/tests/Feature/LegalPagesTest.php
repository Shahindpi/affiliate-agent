<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LegalPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'status' => true]);
        $this->admin = User::create(['name' => 'Legal editor', 'username' => 'legal-editor', 'email' => 'legal@example.test', 'password' => 'secret', 'role_id' => $role->id, 'status' => true]);
    }

    public function test_seeded_legal_pages_are_editable_and_published_without_overwriting_existing_changes(): void
    {
        $this->seed(LegalPageSeeder::class);
        $this->assertSame(3, Page::count());
        $page = Page::where('legal_key', 'privacy-policy')->firstOrFail();
        $page->update(['title' => 'Our privacy details']);
        $this->seed(LegalPageSeeder::class);
        $this->assertSame('Our privacy details', $page->fresh()->title);
        $this->getJson('/api/v1/public/pages')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/public/pages/privacy-policy')->assertOk()->assertJsonPath('data.title', 'Our privacy details');
    }

    public function test_admin_can_edit_legal_page_and_unpublish_it_without_exposing_unsafe_html(): void
    {
        $this->seed(LegalPageSeeder::class);
        $page = Page::where('legal_key', 'terms')->firstOrFail();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/pages')->assertOk()->assertJsonPath('data.total', 3);
        $payload = ['title' => 'New Terms', 'slug' => 'terms-and-conditions', 'excerpt' => 'Updated terms', 'content' => '<h2>Terms</h2><script>alert(1)</script><p onclick="evil()">Safe <a href="javascript:alert(1)">link</a></p>', 'status' => true, 'seo' => ['meta_title' => 'Terms title', 'meta_description' => 'Updated terms']];
        $this->putJson('/api/v1/admin/pages/'.$page->id, $payload)->assertOk()->assertJsonPath('data.seo_meta.meta_title', 'Terms title');
        $this->getJson('/api/v1/public/pages/terms-and-conditions')->assertOk()->assertJsonPath('data.slug', 'terms-and-conditions')->assertDontSee('alert(1)')->assertDontSee('onclick');
        $this->getJson('/api/v1/public/pages/terms')->assertOk()->assertJsonPath('data.slug', 'terms-and-conditions');
        $payload['status'] = false;
        $this->putJson('/api/v1/admin/pages/'.$page->id, $payload)->assertOk();
        $this->getJson('/api/v1/public/pages/terms')->assertNotFound();
        $this->getJson('/api/v1/public/pages')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_slug_safety_and_admin_creation(): void
    {
        Sanctum::actingAs($this->admin);
        $payload = ['title' => 'About', 'slug' => 'about-dewdora', 'content' => '<p>About us</p>', 'status' => true, 'seo' => ['meta_title' => 'About Dewdora']];
        $id = $this->postJson('/api/v1/admin/pages', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/public/pages/about-dewdora')->assertOk();
        $this->postJson('/api/v1/admin/pages', $payload)->assertUnprocessable();
        $this->putJson('/api/v1/admin/pages/'.$id, [...$payload, 'slug' => 'contact'])->assertUnprocessable();
    }
}
