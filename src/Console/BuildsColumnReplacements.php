<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Console;

use Kindharika\ApiStarter\Support\ColumnSchema;

trait BuildsColumnReplacements
{
    /**
     * @return array<string, string>
     */
    protected function columnReplacements(ColumnSchema $schema, bool $audit = false): array
    {
        return [
            'fillable' => $schema->fillableCode(),
            'casts' => $schema->castsCode(),
            'properties' => $schema->propertyDocs(),
            'migrationColumns' => $schema->migrationColumns(),
            'storeRules' => $schema->storeRules(),
            'updateRules' => $schema->updateRules(),
            'resourceFields' => $schema->resourceFields(),
            'resourceReturnDoc' => $schema->resourceReturnDoc(),
            'auditableImport' => $audit
                ? "use Kindharika\\ApiStarter\\Audit\\Auditable;\n"
                : '',
            'auditableUse' => $audit ? "    use Auditable;\n" : '',
        ];
    }
}
