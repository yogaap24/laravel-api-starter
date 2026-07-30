<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

use Illuminate\Http\Request;

/**
 * Typed datatable / list filter — common ground for services & controllers.
 *
 * Prefer DatatableFilter::fromRequest($request) over raw $request->all().
 */
final class DatatableFilter
{
    /**
     * @param  list<string>  $searchColumns
     * @param  list<string>  $filterColumns
     * @param  list<string>  $filterKeys
     */
    public function __construct(
        public readonly int $entries = 15,
        public readonly string $searchKey = '',
        public readonly array $searchColumns = [],
        public readonly string $sortColumn = 'created_at',
        public readonly string $sortType = 'desc',
        public readonly array $filterColumns = [],
        public readonly array $filterKeys = [],
        public readonly ?string $filterDateColumn = null,
        public readonly ?string $filterDateStart = null,
        public readonly ?string $filterDateEnd = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, string|int|float|bool|null> $input */
        $input = $request->query();

        return self::fromArray($input);
    }

    /**
     * @param  array<string, string|int|float|bool|null|array<int, string>>  $input
     */
    public static function fromArray(array $input): self
    {
        $defaultPerPage = (int) config('api-starter.datatable.per_page', 15);
        $defaultSort = (string) config('api-starter.datatable.default_sort_column', 'created_at');
        $defaultDir = (string) config('api-starter.datatable.default_sort_direction', 'desc');

        $entries = isset($input['entries']) ? (int) $input['entries'] : $defaultPerPage;
        if ($entries < 1) {
            $entries = $defaultPerPage;
        }

        $sortType = strtolower((string) ($input['sort_type'] ?? $defaultDir));
        if (! in_array($sortType, ['asc', 'desc'], true)) {
            $sortType = $defaultDir;
        }

        return new self(
            entries: $entries,
            searchKey: (string) ($input['search_key'] ?? ''),
            searchColumns: self::csv((string) ($input['search_columns'] ?? '')),
            sortColumn: (string) ($input['sort_column'] ?? $defaultSort),
            sortType: $sortType,
            filterColumns: self::csv((string) ($input['filter_columns'] ?? '')),
            filterKeys: self::csv((string) ($input['filter_keys'] ?? '')),
            filterDateColumn: self::nullableString($input['filter_date_column'] ?? null),
            filterDateStart: self::nullableString($input['filter_date_start'] ?? null),
            filterDateEnd: self::nullableString($input['filter_date_end'] ?? null),
        );
    }

    /**
     * Array shape accepted by Builder::datatable() macro.
     *
     * @return array{
     *     entries: int,
     *     search_key: string,
     *     search_columns: string,
     *     sort_column: string,
     *     sort_type: string,
     *     filter_columns: string,
     *     filter_keys: string,
     *     filter_date_column: string|null,
     *     filter_date_start: string|null,
     *     filter_date_end: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries,
            'search_key' => $this->searchKey,
            'search_columns' => implode(',', $this->searchColumns),
            'sort_column' => $this->sortColumn,
            'sort_type' => $this->sortType,
            'filter_columns' => implode(',', $this->filterColumns),
            'filter_keys' => implode(',', $this->filterKeys),
            'filter_date_column' => $this->filterDateColumn,
            'filter_date_start' => $this->filterDateStart,
            'filter_date_end' => $this->filterDateEnd,
        ];
    }

    /**
     * @return list<string>
     */
    private static function csv(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }

    private static function nullableString(string|int|float|bool|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
