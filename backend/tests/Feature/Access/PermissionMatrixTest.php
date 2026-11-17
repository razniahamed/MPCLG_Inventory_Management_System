<?php

use App\Services\Access\PermissionResolver;
use App\Support\Access\Catalogue;

/*
 * Permission matrix: every seeded role resolves to exactly its catalogue
 * permission set — nothing more, nothing less — and the HTTP layer agrees.
 */

dataset('roles', array_keys(Catalogue::roles()));

it('resolves exactly the catalogue permissions for each role', function (string $role) {
    $user = $this->userWithRoles([$role]);
    $resolver = app(PermissionResolver::class);

    $expected = Catalogue::expand(Catalogue::roles()[$role][2]);
    $actual = array_keys($resolver->effectivePermissions($user));

    expect($actual)->toEqualCanonicalizing($expected);
})->with('roles');

it('never gives approval permissions to system_admin or internal_auditor', function (string $role) {
    $user = $this->userWithRoles([$role]);
    $resolver = app(PermissionResolver::class);

    foreach (Catalogue::SENSITIVE as $code) {
        expect($resolver->can($user, $code))->toBeFalse("{$role} must not hold {$code}");
    }
})->with(['system_admin', 'internal_auditor']);

it('enforces the matrix on real endpoints', function (string $role, string $method, string $uri, bool $allowed) {
    $this->actingAsUser($this->userWithRoles([$role]));

    $response = $this->json($method, "/api/v1/{$uri}");

    if ($allowed) {
        expect($response->status())->not->toBe(403);
    } else {
        $this->assertApiError($response, 403, 'PERMISSION_DENIED');
    }
})->with([
    'admin lists users' => ['system_admin', 'GET', 'users', true],
    'admin reads audit' => ['system_admin', 'GET', 'audit-logs', true],
    'admin manages settings' => ['system_admin', 'GET', 'settings', true],
    'auditor lists users' => ['internal_auditor', 'GET', 'users', true],
    'auditor reads audit' => ['internal_auditor', 'GET', 'audit-logs', true],
    'auditor cannot create users' => ['internal_auditor', 'POST', 'users', false],
    'auditor cannot manage settings' => ['internal_auditor', 'GET', 'settings', false],
    'storekeeper reads stores' => ['storekeeper', 'GET', 'stores', true],
    'storekeeper cannot list users' => ['storekeeper', 'GET', 'users', false],
    'storekeeper cannot create stores' => ['storekeeper', 'POST', 'stores', false],
    'chief clerk reads officers' => ['chief_clerk', 'GET', 'officers', true],
    'chief clerk cannot manage officers' => ['chief_clerk', 'POST', 'officers', false],
    'staff cannot read departments' => ['staff', 'GET', 'departments', false],
    'staff cannot read audit' => ['staff', 'GET', 'audit-logs', false],
    'cfo cannot manage roles' => ['cfo', 'POST', 'roles', false],
    'cfo cannot toggle modules' => ['cfo', 'GET', 'modules', false],
]);
