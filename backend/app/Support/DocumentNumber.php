<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe document numbers, e.g. GRN-2026-000123 (Section 7.2 document_sequences).
 */
class DocumentNumber
{
    public static function next(string $docType, ?int $year = null, string $scopeKey = 'ALL', int $pad = 6): string
    {
        $year ??= (int) now(config('mpclg.display_timezone'))->format('Y');

        return DB::transaction(function () use ($docType, $year, $scopeKey, $pad) {
            $sequence = DocumentSequence::where(['doc_type' => $docType, 'year' => $year, 'scope_key' => $scopeKey])
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DocumentSequence::insertOrIgnore([
                    'doc_type' => $docType, 'year' => $year, 'scope_key' => $scopeKey,
                    'prefix' => $docType, 'last_number' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $sequence = DocumentSequence::where(['doc_type' => $docType, 'year' => $year, 'scope_key' => $scopeKey])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->increment('last_number');

            $scope = $scopeKey === 'ALL' ? '' : "{$scopeKey}-";

            return sprintf('%s-%s%d-%s', $sequence->prefix ?? $docType, $scope, $year, str_pad((string) $sequence->last_number, $pad, '0', STR_PAD_LEFT));
        });
    }
}
