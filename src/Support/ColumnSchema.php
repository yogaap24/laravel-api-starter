<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Parses / prompts model columns and renders stub fragments.
 *
 * Spec: name:type, name:type?, name:string:100, name:decimal:10,2,
 *       status:enum:a|b|c, tags:set:a|b, user_id:foreignUuid:users
 *
 * Covers Laravel Blueprint column types (see {@see self::supportedTypes()}).
 * Note: `timestamps` / `timestampsTz` as a COLUMN type map to single
 * `timestamp` / `timestampTz` (created_at/updated_at already added by migration stub).
 */
final class ColumnSchema
{
    /**
     * Canonical Blueprint method names this package can generate.
     *
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [
            // strings
            'char', 'string', 'text', 'mediumText', 'longText',
            // integers
            'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
            'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
            'unsignedMediumInteger', 'unsignedBigInteger',
            // floats
            'float', 'double', 'decimal', 'unsignedDecimal',
            // boolean
            'boolean',
            // dates / times
            'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz',
            'timestamp', 'timestampTz', 'year',
            // structured
            'json', 'jsonb', 'enum', 'set',
            // binary / network / id
            'binary', 'uuid', 'ulid', 'ipAddress', 'macAddress',
            // spatial (DB-dependent)
            'geometry', 'point', 'lineString', 'polygon',
            'geometryCollection', 'multiPoint', 'multiLineString', 'multiPolygon',
            // relations
            'foreignId', 'foreignUuid', 'foreignUlid',
        ];
    }

    /**
     * @param  list<ColumnDefinition>  $columns
     */
    public function __construct(
        public readonly array $columns,
    ) {
        if ($this->columns === []) {
            throw new InvalidArgumentException('Column schema cannot be empty.');
        }
    }

    public static function defaults(): self
    {
        return new self([
            new ColumnDefinition('name', 'string', false),
            new ColumnDefinition('description', 'text', true),
        ]);
    }

    public static function parse(string $spec): self
    {
        $spec = trim($spec);
        if ($spec === '') {
            return self::defaults();
        }

        $columns = [];
        foreach (self::splitSpec($spec) as $part) {
            $columns[] = self::parseOne($part);
        }

        return $columns === [] ? self::defaults() : new self($columns);
    }

    /**
     * Split on commas that are not part of decimal precision (e.g. decimal:10,2).
     *
     * @return list<string>
     */
    private static function splitSpec(string $spec): array
    {
        $parts = [];
        $buffer = '';
        $len = strlen($spec);

        for ($i = 0; $i < $len; $i++) {
            $char = $spec[$i];
            if ($char === ',' && ! preg_match('/(?:unsigned)?decimal:\d+$/i', $buffer) && ! preg_match('/(?:float|double):\d+$/i', $buffer)) {
                $trim = trim($buffer);
                if ($trim !== '') {
                    $parts[] = $trim;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $trim = trim($buffer);
        if ($trim !== '') {
            $parts[] = $trim;
        }

        return $parts;
    }

    public static function fromInteractive(Command $command): self
    {
        $types = self::supportedTypes();
        $command->info('Define model columns (empty name = done).');
        $command->comment('Types: ' . implode(', ', $types));
        $command->comment('Enum/set values: a;b;c (prefer ;) or a|b|c — quote --columns in shell');
        $command->comment('timestamps as column → timestamp (created_at/updated_at already in stub)');

        $columns = [];
        while (true) {
            $name = trim((string) $command->ask('Column name (blank to finish)'));
            if ($name === '') {
                break;
            }
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                $command->warn('Use snake_case starting with a letter.');
                continue;
            }

            $type = self::normalizeType(trim((string) $command->anticipate('Type', $types, 'string')));
            if (! in_array($type, $types, true)) {
                $command->warn("Unknown type [{$type}]");
                continue;
            }

            $nullable = (bool) $command->confirm('Nullable?', false);
            $length = null;
            $precision = null;
            $scale = null;
            $foreignTable = null;
            $enumValues = null;

            if (in_array($type, ['string', 'char'], true)) {
                $length = trim((string) $command->ask('Length', '255')) ?: '255';
            }
            if (in_array($type, ['decimal', 'unsignedDecimal', 'float', 'double'], true)) {
                $precision = trim((string) $command->ask('Precision / total', '10')) ?: '10';
                $scale = trim((string) $command->ask('Scale / places', '2')) ?: '2';
            }
            if (in_array($type, ['foreignId', 'foreignUuid', 'foreignUlid'], true)) {
                $foreignTable = trim((string) $command->ask('Foreign table', 'users')) ?: 'users';
            }
            if (in_array($type, ['enum', 'set'], true)) {
                $raw = trim((string) $command->ask('Values (a;b;c or a|b|c)', ''));
                $enumValues = self::splitEnumValues($raw);
                if ($enumValues === []) {
                    $command->warn("No values — [{$name}] falls back to string(64).");
                }
            }

            $columns[] = new ColumnDefinition($name, $type, $nullable, $length, $precision, $scale, $foreignTable, $enumValues);
        }

        return $columns === [] ? self::defaults() : new self($columns);
    }

    public static function resolve(?string $columnsOption, Command $command): self
    {
        if (is_string($columnsOption) && trim($columnsOption) !== '') {
            return self::parse($columnsOption);
        }

        if ($command->input->isInteractive() && $command->confirm('Input custom columns? (no = name + description)', false)) {
            return self::fromInteractive($command);
        }

        return self::defaults();
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (ColumnDefinition $c): string => $c->name, $this->columns);
    }

    public function fillableCode(): string
    {
        $lines = array_map(
            static fn (ColumnDefinition $c): string => "        '{$c->name}',",
            $this->columns
        );

        return implode("\n", $lines);
    }

    public function castsCode(): string
    {
        $casts = [];
        foreach ($this->columns as $c) {
            $cast = self::eloquentCast($c->type);
            if ($cast !== null) {
                $casts[] = "        '{$c->name}' => '{$cast}',";
            }
        }

        return $casts === [] ? '        //' : implode("\n", $casts);
    }

    public function propertyDocs(): string
    {
        $lines = [
            ' * @property string $id',
        ];
        foreach ($this->columns as $c) {
            $lines[] = ' * @property ' . $this->phpDocType($c) . ' $' . $c->name;
        }
        $lines[] = ' * @property \\Illuminate\\Support\\Carbon|null $created_at';
        $lines[] = ' * @property \\Illuminate\\Support\\Carbon|null $updated_at';
        $lines[] = ' * @property \\Illuminate\\Support\\Carbon|null $deleted_at';

        return implode("\n", $lines);
    }

    public function migrationColumns(): string
    {
        $lines = [];
        foreach ($this->columns as $c) {
            $lines[] = '            ' . $this->migrationLine($c);
        }

        return implode("\n", $lines);
    }

    public function storeRules(): string
    {
        $lines = [];
        foreach ($this->columns as $c) {
            $lines[] = "            '{$c->name}' => [{$this->validationRules($c, false)}],";
        }

        return implode("\n", $lines);
    }

    public function updateRules(): string
    {
        $lines = [];
        foreach ($this->columns as $c) {
            $lines[] = "            '{$c->name}' => [{$this->validationRules($c, true)}],";
        }

        return implode("\n", $lines);
    }

    public function resourceFields(): string
    {
        $lines = ["            'id' => \$this->id,"];
        foreach ($this->columns as $c) {
            $lines[] = "            '{$c->name}' => \$this->{$c->name},";
        }
        $lines[] = "            'created_at' => \$this->created_at,";
        $lines[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $lines);
    }

    public function resourceReturnDoc(): string
    {
        $parts = ['id: string'];
        foreach ($this->columns as $c) {
            $parts[] = "{$c->name}: " . $this->phpDocType($c);
        }
        $parts[] = 'created_at: \\Illuminate\\Support\\Carbon|null';
        $parts[] = 'updated_at: \\Illuminate\\Support\\Carbon|null';

        return implode(",\n     *     ", $parts);
    }

    /**
     * OpenAPI 3 resource schema (response body).
     *
     * @return array<string, mixed>
     */
    public function openApiResourceSchema(): array
    {
        $properties = [
            'id' => ['type' => 'string', 'format' => 'uuid'],
        ];
        foreach ($this->columns as $c) {
            $properties[$c->name] = $this->openApiProperty($c);
        }
        $properties['created_at'] = ['type' => 'string', 'format' => 'date-time', 'nullable' => true];
        $properties['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'nullable' => true];

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * OpenAPI 3 store (create) request schema.
     *
     * @return array<string, mixed>
     */
    public function openApiStoreSchema(): array
    {
        $properties = [];
        $required = [];
        foreach ($this->columns as $c) {
            $properties[$c->name] = $this->openApiProperty($c);
            if (! $c->nullable) {
                $required[] = $c->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * OpenAPI 3 update request schema (all fields optional / sometimes).
     *
     * @return array<string, mixed>
     */
    public function openApiUpdateSchema(): array
    {
        $properties = [];
        foreach ($this->columns as $c) {
            $prop = $this->openApiProperty($c);
            $prop['nullable'] = true;
            $properties[$c->name] = $prop;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Example value for search_columns query param.
     */
    public function openApiSearchColumnsExample(): string
    {
        return implode(',', array_slice($this->names(), 0, 3));
    }

    /**
     * @OA\* Property lines for l5-swagger (optional).
     */
    public function openApiAnnotationProperties(string $indent = '     *     '): string
    {
        $lines = [
            $indent . '@OA\Property(property="id", type="string", format="uuid"),',
        ];
        foreach ($this->columns as $c) {
            $lines[] = $indent . $this->openApiAnnotationProperty($c) . ',';
        }
        $lines[] = $indent . '@OA\Property(property="created_at", type="string", format="date-time", nullable=true),';
        $lines[] = $indent . '@OA\Property(property="updated_at", type="string", format="date-time", nullable=true)';

        return implode("\n", $lines);
    }

    /**
     * @OA\* Property lines for store/update request body schema.
     */
    public function openApiAnnotationRequestProperties(string $indent = '     *     '): string
    {
        $lines = [];
        foreach ($this->columns as $c) {
            $lines[] = $indent . $this->openApiAnnotationProperty($c) . ',';
        }
        if ($lines === []) {
            return $indent . '//';
        }
        $last = array_key_last($lines);
        $lines[$last] = rtrim($lines[$last], ',');

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function openApiProperty(ColumnDefinition $c): array
    {
        $prop = match (true) {
            in_array($c->type, ['boolean'], true) => ['type' => 'boolean'],
            self::isIntegerType($c->type) => ['type' => 'integer'],
            in_array($c->type, ['decimal', 'unsignedDecimal', 'float', 'double'], true) => ['type' => 'number'],
            in_array($c->type, ['uuid', 'foreignUuid'], true) => ['type' => 'string', 'format' => 'uuid'],
            in_array($c->type, ['ulid', 'foreignUlid'], true) => ['type' => 'string', 'format' => 'ulid'],
            in_array($c->type, ['date'], true) => ['type' => 'string', 'format' => 'date'],
            self::isDateTimeType($c->type) => ['type' => 'string', 'format' => 'date-time'],
            in_array($c->type, ['time', 'timeTz'], true) => ['type' => 'string', 'format' => 'time', 'example' => '12:00:00'],
            in_array($c->type, ['year'], true) => ['type' => 'integer', 'example' => 2026],
            in_array($c->type, ['json', 'jsonb'], true) => ['type' => 'object'],
            in_array($c->type, ['set'], true) => ['type' => 'array', 'items' => ['type' => 'string']],
            in_array($c->type, ['binary'], true) => ['type' => 'string', 'format' => 'binary'],
            in_array($c->type, ['ipAddress'], true) => ['type' => 'string', 'format' => 'ipv4'],
            in_array($c->type, ['macAddress'], true) => ['type' => 'string', 'example' => '00:1A:2B:3C:4D:5E'],
            in_array($c->type, [
                'geometry', 'point', 'lineString', 'polygon',
                'geometryCollection', 'multiPoint', 'multiLineString', 'multiPolygon',
            ], true) => ['type' => 'object', 'description' => 'Spatial / GeoJSON'],
            in_array($c->type, ['char', 'string'], true) => array_filter([
                'type' => 'string',
                'maxLength' => $c->length !== null ? (int) $c->length : 255,
            ]),
            in_array($c->type, ['text', 'mediumText', 'longText'], true) => ['type' => 'string'],
            in_array($c->type, ['enum'], true) => ['type' => 'string'],
            default => ['type' => 'string'],
        };

        if ($c->type === 'enum' && ($c->enumValues ?? []) !== []) {
            $prop['enum'] = $c->enumValues;
        }
        if ($c->type === 'set' && ($c->enumValues ?? []) !== []) {
            $prop['items'] = ['type' => 'string', 'enum' => $c->enumValues];
        }
        if ($c->nullable) {
            $prop['nullable'] = true;
        }

        return $prop;
    }

    private function openApiAnnotationProperty(ColumnDefinition $c): string
    {
        $parts = ['property="' . $c->name . '"'];
        $schema = $this->openApiProperty($c);

        if (($schema['type'] ?? '') === 'array') {
            $parts[] = 'type="array"';
            $parts[] = '@OA\Items(type="string")';
        } else {
            $parts[] = 'type="' . ($schema['type'] ?? 'string') . '"';
            if (isset($schema['format'])) {
                $parts[] = 'format="' . $schema['format'] . '"';
            }
            if (isset($schema['maxLength'])) {
                $parts[] = 'maxLength=' . (int) $schema['maxLength'];
            }
            if (isset($schema['enum']) && is_array($schema['enum'])) {
                $quoted = implode(',', array_map(
                    static fn (string $v): string => '"' . $v . '"',
                    $schema['enum']
                ));
                $parts[] = 'enum={' . $quoted . '}';
            }
        }
        if (! empty($schema['nullable'])) {
            $parts[] = 'nullable=true';
        }

        return '@OA\Property(' . implode(', ', $parts) . ')';
    }

    private static function parseOne(string $part): ColumnDefinition
    {
        $nullable = str_ends_with($part, '?');
        if ($nullable) {
            $part = substr($part, 0, -1);
        }

        $segments = explode(':', $part);
        $name = trim($segments[0] ?? '');
        $rawType = trim($segments[1] ?? 'string');
        $type = self::normalizeType($rawType);

        if ($name === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid column name in [{$part}]");
        }

        if (! in_array($type, self::supportedTypes(), true)) {
            throw new InvalidArgumentException(
                "Unsupported column type [{$rawType}] for [{$name}]. Supported: " . implode(', ', self::supportedTypes())
            );
        }

        $length = null;
        $precision = null;
        $scale = null;
        $foreignTable = null;
        $enumValues = null;

        if (in_array($type, ['string', 'char'], true) && isset($segments[2])) {
            $length = trim($segments[2]);
        }

        if (in_array($type, ['decimal', 'unsignedDecimal', 'float', 'double'], true)) {
            $precision = trim(str_replace(',', ':', $segments[2] ?? '10'));
            if (str_contains($precision, ':')) {
                [$precision, $scalePart] = array_pad(explode(':', $precision, 2), 2, '2');
                $scale = trim($scalePart);
            } else {
                $scale = trim($segments[3] ?? '2');
            }
        }

        if (in_array($type, ['foreignId', 'foreignUuid', 'foreignUlid'], true)) {
            $foreignTable = trim($segments[2] ?? 'users');
        }

        if (in_array($type, ['enum', 'set'], true)) {
            $raw = trim($segments[2] ?? '');
            $enumValues = self::splitEnumValues($raw);
            foreach ($enumValues as $value) {
                if (! preg_match('/^[a-zA-Z0-9_\-]+$/', $value)) {
                    throw new InvalidArgumentException("Invalid {$type} value [{$value}] for [{$name}]");
                }
            }
        }

        return new ColumnDefinition($name, $type, $nullable, $length, $precision, $scale, $foreignTable, $enumValues);
    }

    /**
     * Split enum/set values. Prefer `;` (shell-safe); `|` also accepted.
     *
     * @return list<string>
     */
    public static function splitEnumValues(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[|;]+/', $raw) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), $parts),
            static fn (string $v): bool => $v !== ''
        ));
    }

    /**
     * Normalize aliases → canonical Blueprint method name.
     */
    public static function normalizeType(string $type): string
    {
        $key = strtolower(str_replace(['_', '-'], '', $type));

        return match ($key) {
            // strings
            'char' => 'char',
            'string', 'str', 'varchar' => 'string',
            'text' => 'text',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            // integers
            'integer', 'int' => 'integer',
            'tinyinteger', 'tinyint' => 'tinyInteger',
            'smallinteger', 'smallint' => 'smallInteger',
            'mediuminteger', 'mediumint' => 'mediumInteger',
            'biginteger', 'bigint' => 'bigInteger',
            'unsignedinteger', 'uint' => 'unsignedInteger',
            'unsignedtinyinteger' => 'unsignedTinyInteger',
            'unsignedsmallinteger' => 'unsignedSmallInteger',
            'unsignedmediuminteger' => 'unsignedMediumInteger',
            'unsignedbiginteger', 'ubigint' => 'unsignedBigInteger',
            // floats
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'unsigneddecimal' => 'unsignedDecimal',
            // bool
            'boolean', 'bool' => 'boolean',
            // dates — timestamps (plural) = single timestamp column (created_at/updated_at already in stub)
            'date' => 'date',
            'datetime' => 'dateTime',
            'datetimetz' => 'dateTimeTz',
            'time' => 'time',
            'timetz' => 'timeTz',
            'timestamp', 'timestamps' => 'timestamp',
            'timestamptz', 'timestampstz' => 'timestampTz',
            'year' => 'year',
            // structured
            'json', 'array' => 'json',
            'jsonb' => 'jsonb',
            'enum' => 'enum',
            'set' => 'set',
            // other
            'binary' => 'binary',
            'uuid' => 'uuid',
            'ulid' => 'ulid',
            'ipaddress', 'ip' => 'ipAddress',
            'macaddress', 'mac' => 'macAddress',
            // spatial
            'geometry' => 'geometry',
            'point' => 'point',
            'linestring' => 'lineString',
            'polygon' => 'polygon',
            'geometrycollection' => 'geometryCollection',
            'multipoint' => 'multiPoint',
            'multilinestring' => 'multiLineString',
            'multipolygon' => 'multiPolygon',
            // relations
            'foreignid' => 'foreignId',
            'foreignuuid' => 'foreignUuid',
            'foreignulid' => 'foreignUlid',
            default => $type, // keep original; allowed-list will reject unknowns
        };
    }

    private function migrationLine(ColumnDefinition $c): string
    {
        $name = $c->name;
        $table = $c->foreignTable ?? 'users';

        if (in_array($c->type, ['enum', 'set'], true)) {
            $values = $c->enumValues ?? [];
            if ($values === []) {
                $chain = '$table->string(\'' . $name . '\', 64)';
            } else {
                $quoted = implode(', ', array_map(
                    static fn (string $v): string => "'" . str_replace("'", "\\'", $v) . "'",
                    $values
                ));
                $method = $c->type; // enum|set
                $chain = '$table->' . $method . '(\'' . $name . '\', [' . $quoted . '])';
            }
        } elseif (in_array($c->type, ['foreignId', 'foreignUuid', 'foreignUlid'], true)) {
            $method = $c->type;
            $chain = $c->nullable
                ? '$table->' . $method . '(\'' . $name . '\')->nullable()->constrained(\'' . $table . '\')'
                : '$table->' . $method . '(\'' . $name . '\')->constrained(\'' . $table . '\')';

            return $chain . ';';
        } elseif (in_array($c->type, ['decimal', 'unsignedDecimal'], true)) {
            $p = (int) ($c->precision ?? '10');
            $s = (int) ($c->scale ?? '2');
            $chain = '$table->' . $c->type . '(\'' . $name . '\', ' . $p . ', ' . $s . ')';
        } elseif (in_array($c->type, ['float', 'double'], true) && $c->precision !== null) {
            $p = (int) $c->precision;
            $s = (int) ($c->scale ?? '2');
            $chain = '$table->' . $c->type . '(\'' . $name . '\', ' . $p . ', ' . $s . ')';
        } elseif (in_array($c->type, ['string', 'char'], true)) {
            $len = $c->length !== null ? ', ' . (int) $c->length : '';
            $chain = '$table->' . $c->type . '(\'' . $name . '\'' . $len . ')';
        } else {
            // All other Blueprint methods: $table->{type}('name')
            $chain = '$table->' . $c->type . '(\'' . $name . '\')';
        }

        if ($c->nullable) {
            $chain .= '->nullable()';
        }

        return $chain . ';';
    }

    private function validationRules(ColumnDefinition $c, bool $update): string
    {
        $parts = [];
        if ($update) {
            $parts[] = "'sometimes'";
        }
        if ($c->nullable) {
            $parts[] = "'nullable'";
        } else {
            $parts[] = "'required'";
        }

        if (in_array($c->type, ['enum', 'set'], true) && ($c->enumValues ?? []) !== []) {
            $parts[] = "'string'";
            $parts[] = "'in:" . implode(',', $c->enumValues) . "'";

            return implode(', ', $parts);
        }

        $parts[] = match (true) {
            in_array($c->type, ['string', 'char', 'enum', 'set'], true) => "'string', 'max:" . (int) ($c->length ?? '255') . "'",
            in_array($c->type, ['text', 'mediumText', 'longText', 'binary'], true) => "'string'",
            self::isIntegerType($c->type) => "'integer'",
            in_array($c->type, ['boolean'], true) => "'boolean'",
            in_array($c->type, ['decimal', 'unsignedDecimal', 'float', 'double'], true) => "'numeric'",
            in_array($c->type, ['uuid', 'foreignUuid'], true) => "'uuid'",
            in_array($c->type, ['ulid', 'foreignUlid'], true) => "'ulid'",
            in_array($c->type, ['ipAddress'], true) => "'ip'",
            in_array($c->type, ['macAddress'], true) => "'mac_address'",
            in_array($c->type, ['json', 'jsonb'], true) => "'array'",
            in_array($c->type, ['date', 'year'], true) => "'date'",
            self::isDateTimeType($c->type) => "'date'",
            in_array($c->type, ['time', 'timeTz'], true) => "'date_format:H:i:s'",
            default => "'string'",
        };

        return implode(', ', $parts);
    }

    private function phpDocType(ColumnDefinition $c): string
    {
        if ($c->type === 'json' || $c->type === 'jsonb') {
            return $c->nullable ? 'array|null' : 'array';
        }

        $base = match (true) {
            $c->type === 'boolean' => 'bool',
            self::isIntegerType($c->type) => 'int',
            in_array($c->type, ['decimal', 'unsignedDecimal', 'float', 'double'], true) => 'float',
            self::isDateTimeType($c->type) || in_array($c->type, ['date', 'time', 'timeTz', 'year'], true) => '\\Illuminate\\Support\\Carbon',
            default => 'string',
        };

        if (self::isDateTimeType($c->type) || in_array($c->type, ['date', 'time', 'timeTz', 'year'], true)) {
            return $base . '|null';
        }

        return $c->nullable ? $base . '|null' : $base;
    }

    private static function eloquentCast(string $type): ?string
    {
        return match (true) {
            $type === 'boolean' => 'boolean',
            self::isIntegerType($type) => 'integer',
            in_array($type, ['decimal', 'unsignedDecimal'], true) => 'decimal:2',
            in_array($type, ['float', 'double'], true) => 'float',
            in_array($type, ['json', 'jsonb', 'set'], true) => 'array',
            $type === 'date' => 'date',
            self::isDateTimeType($type) || in_array($type, ['time', 'timeTz'], true) => 'datetime',
            $type === 'year' => 'integer',
            default => null,
        };
    }

    private static function isIntegerType(string $type): bool
    {
        return in_array($type, [
            'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
            'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
            'unsignedMediumInteger', 'unsignedBigInteger',
            'foreignId',
        ], true);
    }

    private static function isDateTimeType(string $type): bool
    {
        return in_array($type, [
            'dateTime', 'dateTimeTz', 'timestamp', 'timestampTz',
        ], true);
    }
}
