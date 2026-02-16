<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010007Date20260216000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('project_notes')) {
            $table = $schema->createTable('project_notes');

            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('project_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('title', Types::STRING, [
                'length' => 255,
                'notnull' => true,
            ]);
            $table->addColumn('content', Types::TEXT, [
                'notnull' => true,
            ]);
            $table->addColumn('visibility', Types::STRING, [
                'length' => 20,
                'notnull' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['project_id'], 'idx_notes_project_id');
            $table->addIndex(['user_id'], 'idx_notes_user_id');
            $table->addIndex(['visibility'], 'idx_notes_visibility');
            $table->addIndex(['project_id', 'visibility'], 'idx_notes_project_visibility');

            return $schema;
        }

        return null;
    }
}
