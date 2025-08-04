<?php
declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
class Version0002DateTime20252407400000 extends SimpleMigrationStep {
    public function change(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tableName = 'subscription_history';

        if ($schema->hasTable($tableName)) {
            return null;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->addColumn('subscription_id', 'integer', [
            'notnull' => true,
        ]);
        $table->addColumn('changed_by', 'string', [
            'notnull' => true,
            'length' => 255,
        ]);
        $table->addColumn('change_description', 'string', [
            'notnull' => true,
            'length' => 512,
        ]);
        $table->addColumn('organization_id', 'integer', ['notnull' => true]);
        $table->addColumn('plan_id', 'integer', ['notnull' => true]);
        $table->addColumn('override_max_projects', 'integer', ['notnull' => false]);
        $table->addColumn('override_max_members', 'integer', ['notnull' => false]);
        $table->addColumn('override_quota_per_project', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['notnull' => true, 'length' => 50]);
        $table->addColumn('original_started_at', 'datetime', ['notnull' => true]);
        $table->addColumn('original_expires_at', 'datetime', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(
            ['subscription_id'], 
            'sub_history_sub_id'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('subscriptions'), 
            ['subscription_id'], 
            ['id'], 
            ['onDelete' => 'CASCADE'],
            'fk_sub_history_sub_id'
        );

        return $schema;
    }
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}