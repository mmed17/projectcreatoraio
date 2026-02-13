<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010006Date20260213030000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('custom_projects')) {
            $table = $schema->getTable('custom_projects');

            if ($table->hasColumn('date_start')) {
                $table->dropColumn('date_start');
            }

            if ($table->hasColumn('date_end')) {
                $table->dropColumn('date_end');
            }
        }

        return $schema;
    }
}
