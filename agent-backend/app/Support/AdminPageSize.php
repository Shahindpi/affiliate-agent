<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminPageSize
{
    public static function resolve(Request $request, Builder $query, int $default = 20): int
    {
        if ($request->input('per_page') === 'all') {
            return max(1, (clone $query)->count());
        }

        return min(max($request->integer('per_page', $default), 1), 100);
    }
}
