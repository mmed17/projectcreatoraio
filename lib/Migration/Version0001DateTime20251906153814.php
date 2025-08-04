<?php
declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version0001DateTime20251906153814 extends SimpleMigrationStep {

    /**
     *
     * @param IOutput $output
     * @param Closure $schemaClosure A function that returns the schema
     * @param array $options
     */
    public function change(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tableName = 'custom_projects';

        if ($schema->hasTable($tableName)) {
            return null;
        }

        $table = $schema->createTable($tableName);

        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('name', 'string', [
            'length' => 255,
            'notnull' => true,
        ]);
        $table->addColumn('number', 'string', [
            'length' => 255,
            'notnull' => true,
        ]);
        $table->addColumn('type', 'integer', [
            'notnull' => true,
        ]);
        $table->addColumn('address', 'string', [
            'notnull' => true,
        ]);
        $table->addColumn('description', 'text', [
            'notnull' => true,
        ]);
        $table->addColumn('owner_id', 'string', [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addColumn('circle_id', 'string', [
            'length' => 255,
            'notnull' => true,
        ]);
        $table->addColumn('board_id', 'integer', [
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('folder_id', 'integer', [
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->addColumn('folder_path', 'string', [
            'notnull' => true,
        ]);
        $table->addColumn('status', 'integer', [
            'notnull' => true,
            'default' => 1,
        ]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['name'], 'projectNameIndex');
        $table->addIndex(['owner_id'], 'projectOwnerIdIndex');
        $table->addUniqueIndex(['circle_id'], 'projectCircleIdUnique');

        $table->addForeignKeyConstraint(
            $schema->getTable('users'), 
            ['owner_id'], 
            ['uid'], 
            ['onDelete' => 'CASCADE'], 
            'fk_projects_owner'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('circles_circles'), 
            ['circle_id'], 
            ['circle_id'], 
            ['onDelete' => 'CASCADE'], 
            'fk_projects_circle'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('deck_boards'), 
            ['board_id'], 
            ['id'], 
            ['onDelete' => 'CASCADE'], 
            'fk_projects_board'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('filecache'), 
            ['folder_id'],
            ['fileid'],
            ['onDelete' => 'CASCADE'],
            'fk_projects_folder'
        );

        return $schema;
    }
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}