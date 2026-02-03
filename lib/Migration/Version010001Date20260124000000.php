<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010001Date20260124000000 extends SimpleMigrationStep
{
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('project_timeline_items')) {
            $table = $schema->createTable('project_timeline_items');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);

            $table->addColumn('project_id', Types::BIGINT, [
                'notnull' => true,
            ]);

            $table->addColumn('label', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);

            $table->addColumn('start_date', Types::DATE, [
                'notnull' => false,
            ]);

            $table->addColumn('end_date', Types::DATE, [
                'notnull' => false,
            ]);

            $table->addColumn('color', Types::STRING, [
                'notnull' => true,
                'length' => 20,
                'default' => '#3b82f6',
            ]);

            $table->addColumn('order_index', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['project_id'], 'project_timeline_project_idx');
        }

        return $schema;
    }
}
