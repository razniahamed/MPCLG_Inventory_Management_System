<?php

namespace App\Services\Organisation;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create / update / retire for organisation master records.
 * Master data is never hard-deleted: "delete" = is_active 0 + soft delete.
 */
class MasterDataService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return T
     */
    public function create(string $modelClass, array $data, ?callable $validate = null): Model
    {
        return DB::transaction(function () use ($modelClass, $data, $validate) {
            $reason = $data['reason'] ?? null;
            unset($data['reason']);

            $model = new $modelClass($data);
            if ($validate) {
                $validate($model);
            }
            $model->save();

            $this->audit->log(AuditAction::Created, $model, null, $model->attributesToArray(), $reason);

            return $model->fresh();
        });
    }

    public function update(Model $model, array $data, ?callable $validate = null): Model
    {
        return DB::transaction(function () use ($model, $data, $validate) {
            $reason = $data['reason'] ?? null;
            unset($data['reason']);

            $original = $model->getAttributes();
            $model->fill($data);
            if ($validate) {
                $validate($model);
            }
            $model->save();

            $this->audit->logChanges($model, $original, $reason);

            return $model->fresh();
        });
    }

    /**
     * Retire a master record. Refuses when dependants still use it.
     *
     * @param  array<string, \Closure(): bool>  $dependants  label => "is still in use?"
     */
    public function retire(Model $model, string $reason, array $dependants = []): void
    {
        foreach ($dependants as $label => $inUse) {
            if ($inUse()) {
                throw ApiException::businessRule("This record is still used by active {$label}. Re-assign them first.");
            }
        }

        DB::transaction(function () use ($model, $reason) {
            $model->forceFill(['is_active' => false])->save();
            $model->delete();
            $this->audit->log(AuditAction::Deleted, $model, ['is_active' => true], ['is_active' => false, 'deleted' => true], $reason);
        });
    }
}
