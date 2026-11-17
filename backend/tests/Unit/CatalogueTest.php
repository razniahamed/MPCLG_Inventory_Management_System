<?php

use App\Enums\ScopeType;
use App\Enums\SettingValueType;
use App\Services\Access\Scope;
use App\Support\Access\Catalogue;

it('has unique, well-formed permission codes', function () {
    $codes = Catalogue::allCodes();

    expect($codes)->toHaveCount(count(array_unique($codes)));

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[a-z0-9_]+\.[a-z0-9_]+$/');
    }
});

it('only references known permissions in roles and the sensitive list', function () {
    $all = Catalogue::allCodes();

    foreach (Catalogue::roles() as $role => [, , $entries]) {
        expect(array_diff(Catalogue::expand($entries), $all))->toBe([], "Role {$role} references unknown codes");
    }

    expect(array_diff(Catalogue::SENSITIVE, $all))->toBe([]);
});

it('keeps approval permissions away from system_admin and internal_auditor', function () {
    foreach (['system_admin', 'internal_auditor'] as $role) {
        $codes = Catalogue::expand(Catalogue::roles()[$role][2]);
        expect(array_intersect($codes, Catalogue::SENSITIVE))->toBe([]);
    }
});

it('gives system_admin only access and organisation permissions', function () {
    $codes = Catalogue::expand(Catalogue::roles()['system_admin'][2]);

    foreach ($codes as $code) {
        expect(Catalogue::moduleOf($code))->toBeIn(['access', 'organisation']);
    }
});

it('builds scopes and casts setting values', function () {
    expect(Scope::from('global', 5)->id)->toBeNull()
        ->and(Scope::from(ScopeType::Store, 3)->equals(Scope::store(3)))->toBeTrue()
        ->and(SettingValueType::Boolean->cast('1'))->toBeTrue()
        ->and(SettingValueType::Integer->serialize('7'))->toBe('7')
        ->and(SettingValueType::Json->cast('{"a":1}'))->toBe(['a' => 1]);
});
