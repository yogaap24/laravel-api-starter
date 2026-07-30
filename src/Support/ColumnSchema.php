<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Parses / prompts model columns and renders stub fragments.
 *
 * Spec format (comma-separated):
 *   name:string,description:text?,price:decimal:10,2,is_active:boolean,user_id:foreignUuid:users
 *
 * Suffix `?` = nullable. Types: string, text, integer, bigInteger, boolean,
 * decimal, float, uuid, date, datetime, timestamp, json, foreignId, foreignUuid.
 */
final class ColumnSchema
{
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

    /**
     * Parse CLI --columns=… string.
     */
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
            if ($char === ',' && ! preg_match('/decimal:\d+$/', $buffer)) {
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

    /**
     * Interactive column builder (Enter empty name to finish).
     */
    public static function fromInteractive(Command $command): self
    {
        $command->info('Define model columns (empty name = done). Types: string,text,integer,boolean,decimal,uuid,date,datetime,timestamp,json,foreignUuid');
        $command->comment('Example name: title  type: string  nullable: no');

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

            $type = strtolower(trim((string) $command->anticipate(
                'Type',
                ['string', 'text', 'integer', 'bigInteger', 'boolean', 'decimal', 'float', 'uuid', 'date', 'datetime', 'timestamp', 'json', 'foreignId', 'foreignUuid'],
                'string'
            )));
            $nullable = (bool) $command->confirm('Nullable?', false);

            $length = null;
            $precision = null;
            $scale = null;
            $foreignTable = null;

            if ($type === 'string') {
                $length = trim((string) $command->ask('Length', '255')) ?: '255';
            }
            if ($type === 'decimal') {
                $precision = trim((string) $command->ask('Precision', '10')) ?: '10';
                $scale = trim((string) $command->ask('Scale', '2')) ?: '2';
            }
            if (in_array($type, ['foreignId', 'foreignUuid'], true)) {
                $foreignTable = trim((string) $command->ask('Foreign table', 'users')) ?: 'users';
            }

            $columns[] = new ColumnDefinition($name, $type, $nullable, $length, $precision, $scale, $foreignTable);
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
            $cast = match ($c->type) {
                'boolean' => 'boolean',
                'integer', 'bigInteger', 'foreignId' => 'integer',
                'decimal', 'float' => 'float',
                'json' => 'array',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                default => null,
            };
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
            $php = $this->phpDocType($c);
            $lines[] = " * @property {$php} \${$c->name}";
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
            $rules = $this->validationRules($c, false);
            $lines[] = "            '{$c->name}' => [{$rules}],";
        }

        return implode("\n", $lines);
    }

    public function updateRules(): string
    {
        $lines = [];
        foreach ($this->columns as $c) {
            $rules = $this->validationRules($c, true);
            $lines[] = "            '{$c->name}' => [{$rules}],";
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
        $parts = ["id: string"];
        foreach ($this->columns as $c) {
            $parts[] = "{$c->name}: " . $this->phpDocType($c);
        }
        $parts[] = 'created_at: \\Illuminate\\Support\\Carbon|null';
        $parts[] = 'updated_at: \\Illuminate\\Support\\Carbon|null';

        return implode(",\n     *     ", $parts);
    }

    private static function parseOne(string $part): ColumnDefinition
    {
        // name:type or name:type? or name:decimal:10,2 or name:foreignUuid:users?
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

        $length = null;
        $precision = null;
        $scale = null;
        $foreignTable = null;

        if ($type === 'string' && isset($segments[2])) {
            $length = trim($segments[2]);
        }
        if ($type === 'decimal') {
            // Supports decimal:10:2 or decimal:10,2
            $precision = trim(str_replace(',', ':', $segments[2] ?? '10'));
            if (str_contains($precision, ':')) {
                [$precision, $scalePart] = array_pad(explode(':', $precision, 2), 2, '2');
                $scale = trim($scalePart);
            } else {
                $scale = trim($segments[3] ?? '2');
            }
        }
        if (in_array($type, ['foreignId', 'foreignUuid'], true)) {
            $foreignTable = trim($segments[2] ?? 'users');
        }

        $allowed = [
            'string', 'text', 'integer', 'bigInteger', 'boolean', 'decimal', 'float',
            'uuid', 'date', 'datetime', 'timestamp', 'json', 'foreignId', 'foreignUuid',
        ];
        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported column type [{$type}] for [{$name}]");
        }

        return new ColumnDefinition($name, $type, $nullable, $length, $precision, $scale, $foreignTable);
    }

    private static function normalizeType(string $type): string
    {
        $key = strtolower(str_replace(['_', '-'], '', $type));

        return match ($key) {
            'string', 'str', 'varchar' => 'string',
            'text' => 'text',
            'integer', 'int' => 'integer',
            'bigint', 'biginteger' => 'bigInteger',
            'boolean', 'bool' => 'boolean',
            'decimal' => 'decimal',
            'float', 'double' => 'float',
            'uuid' => 'uuid',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'json', 'array' => 'json',
            'foreignid' => 'foreignId',
            'foreignuuid' => 'foreignUuid',
            default => strtolower($type),
        };
    }

    private function migrationLine(ColumnDefinition $c): string
    {
        $chain = match ($c->type) {
            'string' => '$table->string(\'' . $c->name . '\'' . ($c->length ? ', ' . (int) $c->length : '') . ')',
            'text' => '$table->text(\'' . $c->name . '\')',
            'integer' => '$table->integer(\'' . $c->name . '\')',
            'bigInteger' => '$table->bigInteger(\'' . $c->name . '\')',
            'boolean' => '$table->boolean(\'' . $c->name . '\')',
            'decimal' => '$table->decimal(\'' . $c->name . '\', ' . (int) ($c->precision ?? '10') . ', ' . (int) ($c->scale ?? '2') . ')',
            'float' => '$table->float(\'' . $c->name . '\')',
            'uuid' => '$table->uuid(\'' . $c->name . '\')',
            'date' => '$table->date(\'' . $c->name . '\')',
            'datetime' => '$table->dateTime(\'' . $c->name . '\')',
            'timestamp' => '$table->timestamp(\'' . $c->name . '\')',
            'json' => '$table->json(\'' . $c->name . '\')',
            'foreignId' => '$table->foreignId(\'' . $c->name . '\')->constrained(\'' . ($c->foreignTable ?? 'users') . '\')',
            'foreignUuid' => '$table->foreignUuid(\'' . $c->name . '\')->constrained(\'' . ($c->foreignTable ?? 'users') . '\')',
            default => '$table->string(\'' . $c->name . '\')',
        };

        if ($c->nullable && ! in_array($c->type, ['foreignId', 'foreignUuid'], true)) {
            $chain .= '->nullable()';
        } elseif ($c->nullable && in_array($c->type, ['foreignId', 'foreignUuid'], true)) {
            // foreign* constrained — nullable before constrained is awkward; use nullOnDelete pattern
            $chain = match ($c->type) {
                'foreignId' => '$table->foreignId(\'' . $c->name . '\')->nullable()->constrained(\'' . ($c->foreignTable ?? 'users') . '\')',
                default => '$table->foreignUuid(\'' . $c->name . '\')->nullable()->constrained(\'' . ($c->foreignTable ?? 'users') . '\')',
            };
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
        } elseif (! $update) {
            $parts[] = "'required'";
        } else {
            $parts[] = "'required'";
        }

        $parts[] = match ($c->type) {
            'string' => "'string', 'max:" . (int) ($c->length ?? '255') . "'",
            'text', 'json' => "'string'",
            'integer', 'bigInteger', 'foreignId' => "'integer'",
            'boolean' => "'boolean'",
            'decimal', 'float' => "'numeric'",
            'uuid', 'foreignUuid' => "'uuid'",
            'date' => "'date'",
            'datetime', 'timestamp' => "'date'",
            default => "'string'",
        };

        return implode(', ', $parts);
    }

    private function phpDocType(ColumnDefinition $c): string
    {
        $base = match ($c->type) {
            'boolean' => 'bool',
            'integer', 'bigInteger', 'foreignId' => 'int',
            'decimal', 'float' => 'float',
            'json' => 'array|null',
            'date', 'datetime', 'timestamp' => '\\Illuminate\\Support\\Carbon',
            default => 'string',
        };

        if ($c->type === 'json') {
            return $c->nullable ? 'array|null' : 'array';
        }

        if (in_array($c->type, ['date', 'datetime', 'timestamp'], true)) {
            return $base . '|null';
        }

        return $c->nullable ? $base . '|null' : $base;
    }
}
