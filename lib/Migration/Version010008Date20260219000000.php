<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010008Date20260219000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('project_timeline_items')) {
            $table = $schema->getTable('project_timeline_items');

            if (!$table->hasColumn('system_key')) {
                $table->addColumn('system_key', Types::STRING, [
                    'notnull' => false,
                    'length' => 64,
                    'default' => null,
                ]);
            }

            // Keep index name very short to satisfy DB limits.
            if (!$table->hasIndex('ptl_syskey_uniq') && !$table->hasIndex('timeline_project_system_key_uniq')) {
                $table->addUniqueIndex(['project_id', 'system_key'], 'ptl_syskey_uniq');
            }
        }

        return $schema;
    }
}
