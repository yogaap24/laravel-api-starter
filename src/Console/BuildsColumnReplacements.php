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
        $required = [];
        foreach ($schema->columns as $c) {
            if (! $c->nullable) {
                $required[] = $c->name;
            }
        }
        $requiredAttr = $required === []
            ? ''
            : 'required={"' . implode('","', $required) . '"},';

        return [
            'fillable' => $schema->fillableCode(),
            'casts' => $schema->castsCode(),
            'properties' => $schema->propertyDocs(),
            'migrationColumns' => $schema->migrationColumns(),
            'storeRules' => $schema->storeRules(),
            'updateRules' => $schema->updateRules(),
            'resourceFields' => $schema->resourceFields(),
            'resourceReturnDoc' => $schema->resourceReturnDoc(),
            'openApiResourceProperties' => $schema->openApiAnnotationProperties(),
            'openApiRequestProperties' => $schema->openApiAnnotationRequestProperties(),
            'openApiStoreRequired' => $requiredAttr,
            'auditableImport' => $audit
                ? "use Kindharika\\ApiStarter\\Audit\\Auditable;\n"
                : '',
            'auditableUse' => $audit ? "    use Auditable;\n" : '',
        ];
    }
}
