<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class Pagination
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * Paginates from a filter array rather than the query string so the same
     * call works for HTTP requests and MCP tool arguments.
     */
    public static function paginate(Builder $query, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate(min(max($perPage, 1), self::MAX_PER_PAGE), ['*'], 'page', $page);
    }
}
