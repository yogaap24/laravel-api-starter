<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Support;

/**
 * Single model column definition used by scaffold generators.
 */
final class ColumnDefinition
{
    /**
     * @param  list<string>|null  $enumValues  Values for enum columns (a|b|c)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly ?string $length = null,
        public readonly ?string $precision = null,
        public readonly ?string $scale = null,
        public readonly ?string $foreignTable = null,
        public readonly ?array $enumValues = null,
    ) {}
}
