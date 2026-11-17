<?php

namespace Tests;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Database\Factories\UserFactory;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Seed modules, permissions, roles and organisation data once per run. */
    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wraps each test in a transaction; the permission cache
        // must treat that outer transaction as "not in a transaction".
        config(['mpclg.transaction_baseline' => 1]);
    }

    /**
     * Create an active user holding the given roles.
     *
     * @param  array<int, string|array{0: string, 1: ScopeType|string, 2: int}>  $roles
     *                                                                                   'storekeeper' or ['storekeeper', 'store', 3]
     */
    protected function userWithRoles(array $roles = [], array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        foreach ($roles as $entry) {
            [$code, $scopeType, $scopeId] = is_array($entry) ? $entry + [1 => 'global', 2 => null] : [$entry, 'global', null];

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::where('code', $code)->valueOrFail('id'),
                'scope_type' => $scopeType instanceof ScopeType ? $scopeType : ScopeType::from($scopeType),
                'scope_id' => $scopeId,
                'valid_from' => now()->subMinute(),
                'granted_at' => now(),
            ]);
        }

        return $user;
    }

    /** Create a per-user override directly (bypassing the API). */
    protected function overridePermission(User $user, string $code, string $effect, array $attributes = []): UserPermission
    {
        $override = UserPermission::create($attributes + [
            'user_id' => $user->id,
            'permission_id' => Permission::where('code', $code)->valueOrFail('id'),
            'effect' => $effect,
            'scope_type' => 'global',
            'valid_from' => now()->subMinute(),
            'reason' => 'test',
            'granted_at' => now(),
        ]);

        $user->bumpPermissionVersion();

        return $override;
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRoles(['system_admin'], $attributes);
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    /** Sign in through the real endpoint and return the bearer token. */
    protected function loginToken(User $user, string $password = UserFactory::PASSWORD): string
    {
        $response = $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => $password]);
        $response->assertOk();

        return $response->json('data.token');
    }

    public function withToken(string $token, string $type = 'Bearer')
    {
        // Each request must re-resolve the user from the token.
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }

    protected function assertApiError(TestResponse $response, int $status, string $code): TestResponse
    {
        $response->assertStatus($status)->assertJson(['success' => false, 'code' => $code]);

        return $response;
    }
}
