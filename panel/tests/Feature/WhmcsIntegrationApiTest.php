<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhmcsIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hostvim.whmcs_integration.secret' => 'test-whmcs-secret-32chars-min']);
        Role::query()->create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_rejects_missing_secret_header(): void
    {
        $this->postJson('/api/integrations/whmcs/provision', [
            'email' => 'a@b.com',
            'name' => 'Test',
            'password' => 'secret12',
        ])->assertStatus(401);
    }

    public function test_provision_and_suspend_flow(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];

        $this->getJson('/api/integrations/whmcs/test', $h)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/integrations/whmcs/provision', [
            'email' => 'whmcs@example.com',
            'name' => 'WHMCS User',
            'password' => 'LongPass1',
        ], $h)->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'whmcs@example.com',
            'status' => 'active',
        ]);

        $this->postJson('/api/integrations/whmcs/suspend', [
            'email' => 'whmcs@example.com',
        ], $h)->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'whmcs@example.com',
            'status' => 'suspended',
        ]);
    }

    public function test_provision_rejects_non_fqdn_domain(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];

        $this->postJson('/api/integrations/whmcs/provision', [
            'email' => 'new@example.com',
            'name' => 'T',
            'password' => 'LongPass1',
            'domain' => 'notafqdn',
        ], $h)->assertStatus(422);
    }

    public function test_usage_accounts_requires_auth_and_returns_shape(): void
    {
        $this->getJson('/api/integrations/whmcs/usage/accounts')->assertStatus(401);

        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        $this->getJson('/api/integrations/whmcs/usage/accounts', $h)
            ->assertOk()
            ->assertJsonStructure(['accounts']);
    }

    public function test_sso_mint_and_consume(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        $user = User::factory()->create([
            'email' => 'sso@example.com',
            'password' => Hash::make('secret12'),
            'status' => 'active',
        ]);
        $user->syncRoles(['user']);

        $mint = $this->postJson('/api/integrations/whmcs/sso/mint', ['email' => 'sso@example.com'], $h);
        $mint->assertOk();
        $redirectUrl = (string) $mint->json('redirect_url');
        $this->assertNotSame('', $redirectUrl);
        $parts = parse_url($redirectUrl);
        $this->assertIsArray($parts);
        parse_str((string) ($parts['query'] ?? ''), $q);
        $this->assertNotEmpty($q['t'] ?? null);

        $this->postJson('/api/auth/sso/whmcs-consume', ['token' => $q['t']])
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'expires_at']);
    }

    public function test_sso_mint_admin_and_consume(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        $admin = User::factory()->create([
            'email' => 'admin-sso@example.com',
            'password' => Hash::make('secret12'),
            'status' => 'active',
        ]);
        $admin->syncRoles(['admin']);

        $mint = $this->postJson('/api/integrations/whmcs/sso/mint-admin', ['email' => 'admin-sso@example.com'], $h);
        $mint->assertOk();
        $redirectUrl = (string) $mint->json('redirect_url');
        parse_str((string) (parse_url($redirectUrl)['query'] ?? ''), $q);
        $this->assertNotEmpty($q['t'] ?? null);

        $this->postJson('/api/auth/sso/whmcs-consume', ['token' => $q['t']])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin-sso@example.com');
    }

    public function test_dns_import_zone_requires_auth(): void
    {
        $this->postJson('/api/integrations/whmcs/dns/import-zone', [
            'email' => 'a@b.com',
            'domain' => 'x.com',
            'zone_text' => "@ IN A 1.2.3.4\n",
        ])->assertStatus(401);
    }

    public function test_dns_import_zone_returns_404_for_unknown_user(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        $this->postJson('/api/integrations/whmcs/dns/import-zone', [
            'email' => 'missing@example.com',
            'domain' => 'x.com',
            'zone_text' => "@ IN A 1.2.3.4\n",
        ], $h)->assertStatus(404);
    }

    public function test_change_domain_requires_auth(): void
    {
        $this->postJson('/api/integrations/whmcs/change-domain', [
            'email' => 'a@b.com',
            'old_domain' => 'old.com',
            'new_domain' => 'new.com',
        ])->assertStatus(401);
    }

    public function test_change_domain_returns_404_when_old_domain_not_owned(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        User::factory()->create(['email' => 'cd@example.com', 'status' => 'active']);
        $this->postJson('/api/integrations/whmcs/change-domain', [
            'email' => 'cd@example.com',
            'old_domain' => 'not-yours.com',
            'new_domain' => 'new.com',
        ], $h)->assertStatus(404);
    }

    public function test_service_renew_ok(): void
    {
        $h = ['Authorization' => 'Bearer test-whmcs-secret-32chars-min'];
        User::factory()->create(['email' => 'renew@example.com', 'status' => 'active']);
        $this->postJson('/api/integrations/whmcs/service/renew', [
            'email' => 'renew@example.com',
        ], $h)->assertOk()->assertJsonPath('message', 'renew_acknowledged');
    }
}
