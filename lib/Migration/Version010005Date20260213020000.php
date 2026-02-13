<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010005Date20260213020000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('custom_projects')) {
            $table = $schema->getTable('custom_projects');

            if ($table->hasIndex('custom_projects_circle_idx')) {
                $table->dropIndex('custom_projects_circle_idx');
            }

            if ($table->hasColumn('circle_id')) {
                $table->dropColumn('circle_id');
            }
        }

        return $schema;
    }
}
