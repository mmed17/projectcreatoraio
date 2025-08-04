<?php
declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
class Version0002DateTime20252407150000 extends SimpleMigrationStep {
    public function change(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tableName = 'plans';

        if ($schema->hasTable($tableName)) {
            return null;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->addColumn('name', 'string', [
            'notnull' => true,
            'length' => 255,
            'unique' => true
        ]);
        $table->addColumn('max_projects', 'integer', [
            'notnull' => true,
        ]);
        $table->addColumn('max_members', 'integer', [
            'notnull' => true,
        ]);
        $table->addColumn('quota_per_project', 'integer', [
            'notnull' => true,
        ]);
        $table->addColumn('is_public', 'boolean', [
            'notnull' => true,
            'default' => true,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'plans_name');

        return $schema;
    }
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}

}