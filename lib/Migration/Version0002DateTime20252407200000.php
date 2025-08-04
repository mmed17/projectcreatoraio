<?php
declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
class Version0002Datetime20252407200000 extends SimpleMigrationStep {
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

        $table->addColumn('organization_id', 'integer', [
            'notnull' => true,
        ]);

        $table->addColumn('plan_id', 'integer', [
            'notnull' => true,
        ]);

        $table->addColumn('override_max_projects', 'integer', [
            'notnull' => false,
        ]);

        $table->addColumn('override_max_members', 'integer', [
            'notnull' => false,
        ]);

        $table->addColumn('override_quota_per_project', 'integer', [
            'notnull' => false,
        ]);

        $table->addColumn('status', 'string', [
            'notnull' => true,
            'length' => 50,
            'default' => 'active',
        ]);
        
        $table->addColumn('started_at', 'datetime', [
            'notnull' => true,
        ]);

        $table->addColumn('expires_at', 'datetime', [
            'notnull' => false,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['organization_id'], 'subs_org_id_uniq');
        $table->addForeignKeyConstraint(
            $schema->getTable('organizations'), 
            ['organization_id'], 
            ['id'], 
            ['onDelete' => 'CASCADE'], 
            'fk_subs_org_id'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('plans'), 
            ['plan_id'], 
            ['id'], 
            ['onDelete' => 'RESTRICT'], 
            'fk_subs_plan_id'
        );

        return $schema;
    }
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}

}