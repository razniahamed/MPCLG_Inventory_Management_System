<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes append-only audit_logs rows with a tamper-evident hash chain.
 * Call from services inside the same DB transaction as the change.
 */
class AuditLogger
{
    /** Attributes never written to the audit trail. */
    private const REDACT = ['password', 'mfa_secret', 'remember_token', 'token'];

    public function log(
        AuditAction $action,
        Model|string $auditable,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
        ?int $auditableId = null,
    ): AuditLog {
        [$type, $id] = $auditable instanceof Model
            ? [$this->typeOf($auditable), $auditable->getKey()]
            : [$auditable, $auditableId];

        /** @var User|null $user */
        $user = Auth::user();
        $request = request();

        $row = [
            'event_at' => now(),
            'user_id' => $user?->id,
            'officer_id' => $user?->officer_id,
            'action' => $action->value,
            'auditable_type' => $type,
            'auditable_id' => $id ?? 0,
            'old_values' => $this->clean($old),
            'new_values' => $this->clean($new),
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'request_id' => RequestContext::id(),
        ];

        return DB::transaction(function () use ($row) {
            $prev = AuditLog::query()->orderByDesc('id')->lockForUpdate()->value('row_hash');
            $row['prev_hash'] = $prev;
            $row['row_hash'] = hash('sha256', ($prev ?? '').json_encode([
                $row['event_at']->toIso8601String(), $row['user_id'], $row['action'],
                $row['auditable_type'], $row['auditable_id'], $row['old_values'], $row['new_values'],
                $row['reason'], $row['request_id'],
            ]));

            return AuditLog::create($row);
        });
    }

    /** Old/new values for an updated model, limited to the changed attributes. */
    public function logChanges(Model $model, array $original, ?string $reason = null): ?AuditLog
    {
        $changes = $model->getChanges();
        unset($changes['updated_at'], $changes['updated_by']);

        if ($changes === []) {
            return null;
        }

        $old = array_intersect_key($original, $changes);

        return $this->log(AuditAction::Updated, $model, $old, $changes, $reason);
    }

    public function typeOf(Model $model): string
    {
        return $model->getTable();
    }

    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::REDACT as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v), $values);
    }
}
