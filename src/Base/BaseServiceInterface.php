<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Kindharika\ApiStarter\Support\DatatableFilter;

/**
 * Contract for CRUD + datatable services (api:scaffold / module:scaffold).
 *
 * Common ground: use {@see DatatableFilter} instead of untyped request bags.
 */
interface BaseServiceInterface
{
    /**
     * Paginated datatable listing.
     *
     * @param  DatatableFilter|array<string, string|int|float|bool|null>  $filter
     * @return LengthAwarePaginator<int, Model>
     */
    public function dataTable(DatatableFilter|array $filter): LengthAwarePaginator;

    /**
     * @param  string  $id  UUID (or string PK)
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(string $id): Model;

    /**
     * @param  FormRequest|array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>  $data
     */
    public function create(FormRequest|array $data): Model;

    /**
     * @param  FormRequest|array<string, string|int|float|bool|null|array<int|string, string|int|float|bool|null>>  $data
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(string $id, FormRequest|array $data): Model;

    /**
     * Soft-delete and return the deleted model.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(string $id): Model;
}
