<?php
declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
class Version0002DateTime20252407100000 extends SimpleMigrationStep {
    public function change(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tableName = 'subscriptions';
        
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
        ]);
        $table->addColumn('nextcloud_group_id', 'string', [
            'notnull' => true,
            'length' => 255,
        ]);
    
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['nextcloud_group_id'], 'orgs_group_id');

        return $schema;
    }
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}