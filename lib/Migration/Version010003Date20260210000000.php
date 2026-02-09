<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010003Date20260210000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('proj_private_folders')) {
            $table = $schema->getTable('proj_private_folders');

            if (!$table->hasColumn('folder_path')) {
                $table->addColumn('folder_path', Types::STRING, [
                    'notnull' => false,
                    'length' => 512,
                ]);
            }
        }

        return $schema;
    }
}
