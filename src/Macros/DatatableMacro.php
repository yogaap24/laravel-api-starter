<?php

namespace Kindharika\ApiStarter\Macros;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DatatableMacro
{
    public function register(): void
    {
        Builder::macro('datatable', function (array $request = [], bool $useSort = true) {
            /** @var Builder $query */
            $query = $this;

            $searchColumns = blank($request['search_columns'] ?? null) ? [] : explode(',', $request['search_columns']);
            $searchKey = $request['search_key'] ?? '';
            $sortColumn = $request['sort_column'] ?? 'created_at';
            $sortType = $request['sort_type'] ?? 'desc';
            $filterColumns = blank($request['filter_columns'] ?? null) ? [] : explode(',', $request['filter_columns']);
            $filterKeys = blank($request['filter_keys'] ?? null) ? [] : explode(',', $request['filter_keys']);
            $filterDateColumn = $request['filter_date_column'] ?? null;
            $filterDateStart = $request['filter_date_start'] ?? null;
            $filterDateEnd = $request['filter_date_end'] ?? null;

            if (!empty($searchColumns) && !blank($searchKey)) {
                $query = $query->where(function ($q) use ($searchColumns, $searchKey) {
                    foreach ($searchColumns as $searchColumn) {
                        if (str_contains($searchColumn, '.')) {
                            $dir = Str::camel(Str::beforeLast($searchColumn, '.'));
                            $col = Str::afterLast($searchColumn, '.');
                            $q->orWhereHas($dir, function ($q2) use ($col, $searchKey) {
                                $q2->where($col, 'ILIKE', "%$searchKey%");
                            });
                        } else {
                            $q->orWhere($searchColumn, 'ILIKE', "%$searchKey%");
                        }
                    }
                });
            }

            if (!empty($filterColumns) && !empty($filterKeys)) {
                $query = $query->where(function ($q) use ($filterColumns, $filterKeys) {
                    $size = count($filterColumns);
                    for ($i = 0; $i < $size; $i++) {
                        $filterColumn = $filterColumns[$i];
                        $filterKey = $filterKeys[$i] ?? null;

                        if (str_contains($filterColumn, '.')) {
                            $dir = Str::camel(Str::beforeLast($filterColumn, '.'));
                            $col = Str::afterLast($filterColumn, '.');
                            $q->whereHas($dir, function ($q2) use ($col, $filterKey) {
                                if (str_contains($filterKey, '|')) {
                                    $q2->whereIn($col, explode('|', $filterKey));
                                } elseif ($filterKey === 'null') {
                                    $q2->whereNull($col);
                                } else {
                                    $q2->where($col, $filterKey);
                                }
                            });
                        } else {
                            if (str_contains($filterKey, '|')) {
                                $q->whereIn($filterColumn, explode('|', $filterKey));
                            } elseif ($filterKey === 'null') {
                                $q->whereNull($filterColumn);
                            } else {
                                $q->where($filterColumn, $filterKey);
                            }
                        }
                    }
                });
            }

            if (!blank($filterDateColumn) && (!blank($filterDateStart) || !blank($filterDateEnd))) {
                $query = $query->where(function ($q) use ($filterDateColumn, $filterDateStart, $filterDateEnd) {
                    if (!blank($filterDateStart)) {
                        $q->where($filterDateColumn, '>=', Carbon::parse($filterDateStart)->toDateTimeString());
                    }
                    if (!blank($filterDateEnd)) {
                        $q->where($filterDateColumn, '<=', Carbon::parse($filterDateEnd)->toDateTimeString());
                    }
                });
            }

            if ($useSort) {
                $query->orderBy($sortColumn, strtolower($sortType));
            }

            return $query;
        });
    }
}
