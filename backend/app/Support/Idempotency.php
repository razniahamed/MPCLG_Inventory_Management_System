<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Posting endpoints require an Idempotency-Key header. Repeating the same key
 * returns the original result and never changes stock twice (rule 4).
 */
class Idempotency
{
    public static function keyFrom(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '' || strlen($key) > 80) {
            throw new ApiException('An Idempotency-Key header (up to 80 characters) is required for posting.', 'VALIDATION_ERROR', 422, [
                'Idempotency-Key' => ['Send a unique key per posting attempt, e.g. a UUID.'],
            ]);
        }

        return $key;
    }

    /**
     * @return bool true when this exact posting already happened (caller returns the document unchanged)
     */
    public static function alreadyPosted(Model $document, string $key, string $postedStatus = 'posted'): bool
    {
        if ($document->idempotency_key === $key) {
            return true;
        }

        $used = $document->newQuery()->where('idempotency_key', $key)->whereKeyNot($document->getKey())->exists();
        if ($used) {
            throw ApiException::conflict('This Idempotency-Key was already used for a different document.', 'DUPLICATE_POSTING');
        }

        if ($document->status === $postedStatus || $document->idempotency_key !== null) {
            throw ApiException::conflict('This document has already been posted.', 'DUPLICATE_POSTING');
        }

        return false;
    }
}
