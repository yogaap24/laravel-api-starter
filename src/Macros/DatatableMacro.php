<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Macros;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatatableMacro
{
    public function register(): void
    {
        Builder::macro('datatable', function (array $request = [], bool $useSort = true) {
            /** @var Builder $query */
            $query = $this;

            $searchColumns = blank($request['search_columns'] ?? null) ? [] : explode(',', (string) $request['search_columns']);
            $searchKey = (string) ($request['search_key'] ?? '');
            $sortColumn = $request['sort_column'] ?? config('api-starter.datatable.default_sort_column', 'created_at');
            $sortType = $request['sort_type'] ?? config('api-starter.datatable.default_sort_direction', 'desc');
            $filterColumns = blank($request['filter_columns'] ?? null) ? [] : explode(',', (string) $request['filter_columns']);
            $filterKeys = blank($request['filter_keys'] ?? null) ? [] : explode(',', (string) $request['filter_keys']);
            $filterDateColumn = $request['filter_date_column'] ?? null;
            $filterDateStart = $request['filter_date_start'] ?? null;
            $filterDateEnd = $request['filter_date_end'] ?? null;

            $operator = DatatableMacro::resolveSearchOperator();

            if (! empty($searchColumns) && $searchKey !== '') {
                $escaped = addcslashes($searchKey, '%_\\');
                $query = $query->where(function ($q) use ($searchColumns, $escaped, $operator) {
                    foreach ($searchColumns as $searchColumn) {
                        $searchColumn = trim($searchColumn);
                        if ($searchColumn === '') {
                            continue;
                        }

                        if (str_contains($searchColumn, '.')) {
                            $dir = Str::camel(Str::beforeLast($searchColumn, '.'));
                            $col = Str::afterLast($searchColumn, '.');
                            $q->orWhereHas($dir, function ($q2) use ($col, $escaped, $operator) {
                                $q2->where($col, $operator, "%{$escaped}%");
                            });
                        } else {
                            $q->orWhere($searchColumn, $operator, "%{$escaped}%");
                        }
                    }
                });
            }

            if (! empty($filterColumns) && ! empty($filterKeys)) {
                $query = $query->where(function ($q) use ($filterColumns, $filterKeys) {
                    $size = count($filterColumns);
                    for ($i = 0; $i < $size; $i++) {
                        $filterColumn = trim((string) $filterColumns[$i]);
                        $filterKey = $filterKeys[$i] ?? null;

                        if ($filterColumn === '' || $filterKey === null) {
                            continue;
                        }

                        if (str_contains($filterColumn, '.')) {
                            $dir = Str::camel(Str::beforeLast($filterColumn, '.'));
                            $col = Str::afterLast($filterColumn, '.');
                            $q->whereHas($dir, function ($q2) use ($col, $filterKey) {
                                if (str_contains((string) $filterKey, '|')) {
                                    $q2->whereIn($col, explode('|', (string) $filterKey));
                                } elseif ($filterKey === 'null') {
                                    $q2->whereNull($col);
                                } else {
                                    $q2->where($col, $filterKey);
                                }
                            });
                        } else {
                            if (str_contains((string) $filterKey, '|')) {
                                $q->whereIn($filterColumn, explode('|', (string) $filterKey));
                            } elseif ($filterKey === 'null') {
                                $q->whereNull($filterColumn);
                            } else {
                                $q->where($filterColumn, $filterKey);
                            }
                        }
                    }
                });
            }

            if (! blank($filterDateColumn) && (! blank($filterDateStart) || ! blank($filterDateEnd))) {
                $query = $query->where(function ($q) use ($filterDateColumn, $filterDateStart, $filterDateEnd) {
                    if (! blank($filterDateStart)) {
                        $q->where($filterDateColumn, '>=', Carbon::parse($filterDateStart)->toDateTimeString());
                    }
                    if (! blank($filterDateEnd)) {
                        $q->where($filterDateColumn, '<=', Carbon::parse($filterDateEnd)->toDateTimeString());
                    }
                });
            }

            if ($useSort) {
                $direction = strtolower((string) $sortType) === 'asc' ? 'asc' : 'desc';
                $query->orderBy((string) $sortColumn, $direction);
            }

            return $query;
        });
    }

    public static function resolveSearchOperator(): string
    {
        $configured = config('api-starter.datatable.search_operator', 'auto');

        if ($configured === 'ilike' || $configured === 'like') {
            return $configured;
        }

        $driver = DB::connection()->getDriverName();

        return in_array($driver, ['pgsql'], true) ? 'ilike' : 'like';
    }
}
