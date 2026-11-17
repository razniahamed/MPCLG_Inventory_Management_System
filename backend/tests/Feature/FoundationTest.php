<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Str;

it('returns the standard envelope from the health endpoint', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.database', 'ok')
        ->assertJsonStructure(['success', 'message', 'data', 'meta' => ['request_id']])
        ->assertHeader('X-Request-Id');

    expect($response->json('meta.request_id'))->toBe($response->headers->get('X-Request-Id'));
});

it('echoes a valid incoming request id', function () {
    $id = (string) Str::ulid();

    $this->getJson('/api/v1/health', ['X-Request-Id' => $id])
        ->assertHeader('X-Request-Id', $id)
        ->assertJsonPath('meta.request_id', $id);
});

it('uses the error envelope for unknown routes and unauthenticated calls', function () {
    $this->assertApiError($this->getJson('/api/v1/nothing-here'), 404, 'NOT_FOUND');
    $this->assertApiError($this->getJson('/api/v1/users'), 401, 'UNAUTHENTICATED')
        ->assertJsonStructure(['meta' => ['request_id']]);
});

it('returns JSON even when the client asks for HTML', function () {
    $this->get('/api/v1/users', ['Accept' => 'text/html'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('uses the validation error envelope', function () {
    $this->assertApiError($this->postJson('/api/v1/auth/login', []), 422, 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['username', 'password']]);
});

it('keeps audit logs append-only and hash-chained', function () {
    $logger = app(AuditLogger::class);
    $first = $logger->log(AuditAction::Created, 'test_records', null, ['a' => 1], 'first', 1);
    $second = $logger->log(AuditAction::Updated, 'test_records', ['a' => 1], ['a' => 2], 'second', 1);

    expect($second->prev_hash)->toBe($first->row_hash)
        ->and($second->row_hash)->toHaveLength(64);

    expect(fn () => $first->update(['reason' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
    expect(AuditLog::find($first->id)->reason)->toBe('first');
});

it('generates sequential document numbers per type and year', function () {
    expect(DocumentNumber::next('GRN', 2026))->toBe('GRN-2026-000001')
        ->and(DocumentNumber::next('GRN', 2026))->toBe('GRN-2026-000002')
        ->and(DocumentNumber::next('REQ', 2026))->toBe('REQ-2026-000001')
        ->and(DocumentNumber::next('GRN', 2027))->toBe('GRN-2027-000001');
});
