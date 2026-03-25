<?php

namespace LibreNMS\Tests\Feature;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Credentials\SnmpV2cCredentialType;
use LibreNMS\Tests\TestCase;

class CredentialApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:u8n8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f8f=']);
        $this->user = User::factory()->create();
        $apiToken = \App\Models\ApiToken::generateToken($this->user, 'Test Token');
        $this->token = $apiToken->token_hash;
    }

    public function test_can_list_credentials(): void
    {
        Gate::before(fn () => true);

        Credential::create([
            'name' => 'Test Cred',
            'type' => SnmpV2cCredentialType::class,
            'data' => ['community' => 'secret'],
            'is_default' => true,
        ]);

        $response = $this->withHeader('X-Auth-Token', $this->token)
            ->getJson('/api/v0/credentials');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'credentials')
            ->assertJsonFragment(['name' => 'Test Cred', 'is_default' => true])
            ->assertJsonFragment(['community' => '********']);
    }

    public function test_can_create_credential(): void
    {
        Gate::before(fn () => true);

        $response = $this->withHeader('X-Auth-Token', $this->token)
            ->postJson('/api/v0/credentials', [
                'name' => 'New Cred',
                'type' => SnmpV2cCredentialType::class,
                'data' => ['community' => 'new_secret'],
                'is_default' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('credentials', ['name' => 'New Cred', 'is_default' => true]);
    }

    public function test_unmask_endpoint_works(): void
    {
        $credential = Credential::create([
            'name' => 'Secret Cred',
            'type' => SnmpV2cCredentialType::class,
            'data' => ['community' => 'topsecret'],
        ]);

        Gate::before(fn () => true);

        $response = $this->withHeader('X-Auth-Token', $this->token)
            ->getJson("/api/v0/credentials/{$credential->id}/unmask/community");

        $response->assertStatus(200);
        $response->assertJsonPath('value', 'topsecret');
    }

    public function test_unmask_endpoint_denies_without_permission(): void
    {
        $credential = Credential::create([
            'name' => 'Secret Cred',
            'type' => SnmpV2cCredentialType::class,
            'data' => ['community' => 'topsecret'],
        ]);

        // Don't use Gate::before(fn() => true);
        // By default, User::factory() created user doesn't have permissions unless we grant them.

        $response = $this->withHeader('X-Auth-Token', $this->token)
            ->getJson("/api/v0/credentials/{$credential->id}/unmask/community");

        // Should fail due to Gate::authorize('credential.unmask')
        $response->assertStatus(403);
    }
}
