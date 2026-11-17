<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Standard list handling (Section 4):
 *   ?page=1&per_page=25&sort=-created_at&filter[status]=active&search=perera
 */
class ListQuery
{
    /**
     * @param  array<string, string|\Closure>  $filters  filter key => column, or closure(Builder, value)
     * @param  array<int, string>  $sorts  allowed sort columns
     * @param  array<int, string>  $search  columns matched with LIKE
     */
    public static function paginate(
        Builder $query,
        Request $request,
        array $filters = [],
        array $sorts = ['id'],
        array $search = [],
        string $defaultSort = 'id',
    ): LengthAwarePaginator {
        foreach ((array) $request->query('filter', []) as $key => $value) {
            if (! array_key_exists($key, $filters)) {
                throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                    "filter.{$key}" => ['Unsupported filter. Allowed: '.implode(', ', array_keys($filters)).'.'],
                ]);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $filter = $filters[$key];
            if ($filter instanceof \Closure) {
                $filter($query, $value);
            } elseif (in_array(strtolower((string) $value), ['true', 'false'], true)) {
                $query->where($filter, strtolower((string) $value) === 'true');
            } elseif (is_string($value) && str_contains($value, ',')) {
                $query->whereIn($filter, explode(',', $value));
            } else {
                $query->where($filter, $value);
            }
        }

        $term = trim((string) $request->query('search', ''));
        if ($term !== '' && $search !== []) {
            $query->where(function ($q) use ($search, $term) {
                foreach ($search as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        $sort = (string) $request->query('sort', $defaultSort);
        foreach (array_filter(explode(',', $sort)) as $part) {
            $direction = str_starts_with($part, '-') ? 'desc' : 'asc';
            $column = ltrim($part, '-');
            if (! in_array($column, $sorts, true)) {
                throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                    'sort' => ['Unsupported sort. Allowed: '.implode(', ', $sorts).'.'],
                ]);
            }
            $query->orderBy($column, $direction);
        }

        $max = config('mpclg.pagination.max_per_page');
        $perPage = min($max, max(1, (int) $request->query('per_page', config('mpclg.pagination.default_per_page'))));

        return $query->paginate($perPage)->withQueryString();
    }
}
