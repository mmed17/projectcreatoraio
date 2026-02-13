<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010004Date20260213010000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('custom_projects')) {
            $table = $schema->getTable('custom_projects');

            if (!$table->hasColumn('project_group_gid')) {
                $table->addColumn('project_group_gid', Types::STRING, [
                    'notnull' => false,
                    'length' => 64,
                    'default' => null,
                ]);
            }

            if (!$table->hasIndex('custom_projects_group_gid_idx')) {
                $table->addIndex(['project_group_gid'], 'custom_projects_group_gid_idx');
            }
        }

        return $schema;
    }
}
