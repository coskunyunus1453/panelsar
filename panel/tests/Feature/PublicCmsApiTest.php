<?php

namespace Tests\Feature;

use App\Models\CmsItem;
use App\Models\HostingPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pricing_requires_no_auth(): void
    {
        HostingPackage::query()->create([
            'name' => 'Test',
            'slug' => 'test-pkg-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
            'price_monthly' => 9.99,
            'price_yearly' => 99,
            'currency' => 'USD',
        ]);

        $response = $this->getJson('/api/public/pricing');

        $response->assertOk()
            ->assertJsonStructure(['packages']);
    }

    public function test_public_cms_landing_returns_json(): void
    {
        $response = $this->getJson('/api/public/cms/landing');

        $response->assertOk()
            ->assertJsonStructure(['page']);
    }

    public function test_public_docs_list_returns_json(): void
    {
        $response = $this->getJson('/api/public/docs');

        $response->assertOk()
            ->assertJsonStructure(['items']);
    }

    public function test_public_doc_show_404_when_missing(): void
    {
        $response = $this->getJson('/api/public/docs/nonexistent-slug-xyz');

        $response->assertNotFound();
    }

    public function test_set_api_locale_header_affects_fallback(): void
    {
        CmsItem::query()->create([
            'kind' => CmsItem::KIND_LANDING,
            'slug' => '',
            'locale' => 'tr',
            'title' => 'TR başlık',
            'body_markdown' => '# Merhaba',
            'status' => 'published',
            'published_at' => now(),
            'is_published' => true,
        ]);

        $response = $this->withHeaders(['X-Locale' => 'tr'])
            ->getJson('/api/public/cms/landing');

        $response->assertOk();
        $this->assertSame('TR başlık', $response->json('page.title'));
    }

    public function test_admin_cms_items_requires_auth(): void
    {
        $response = $this->getJson('/api/admin/cms-items?kind=landing');

        $response->assertUnauthorized();
    }

    public function test_admin_cms_kind_alias_requires_auth(): void
    {
        $response = $this->getJson('/api/admin/cms/landing');

        $response->assertUnauthorized();
    }
}
