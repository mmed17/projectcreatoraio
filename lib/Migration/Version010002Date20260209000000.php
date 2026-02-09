<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to create custom_projects table or add missing columns
 */
class Version010002Date20260209000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create the table if it doesn't exist
        if (!$schema->hasTable('custom_projects')) {
            $table = $schema->createTable('custom_projects');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);

            // Project Details
            $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('label', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('number', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('type', Types::INTEGER, ['notnull' => false]);
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);

            // Client Info
            $table->addColumn('client_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('client_role', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('client_phone', Types::STRING, ['notnull' => false, 'length' => 50]);
            $table->addColumn('client_email', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('client_address', Types::TEXT, ['notnull' => false]);

            // Location Info
            $table->addColumn('loc_street', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('loc_city', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('loc_zip', Types::STRING, ['notnull' => false, 'length' => 20]);
            $table->addColumn('external_ref', Types::STRING, ['notnull' => false, 'length' => 255]);

            // Timeline
            $table->addColumn('date_start', Types::DATE, ['notnull' => false]);
            $table->addColumn('date_end', Types::DATE, ['notnull' => false]);

            // System Fields
            $table->addColumn('owner_id', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('circle_id', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('board_id', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('folder_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('folder_path', Types::STRING, ['notnull' => false, 'length' => 512]);
            $table->addColumn('status', Types::INTEGER, ['notnull' => false, 'default' => 0]);
            $table->addColumn('organization_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('white_board_id', Types::STRING, ['notnull' => false, 'length' => 64]);

            // Timestamps
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => false]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner_id'], 'custom_projects_owner_idx');
            $table->addIndex(['organization_id'], 'custom_projects_org_idx');
            $table->addIndex(['circle_id'], 'custom_projects_circle_idx');

        } else {
            // Table exists, add missing columns
            $table = $schema->getTable('custom_projects');

            $columnsToAdd = [
                'number' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'label' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'type' => [Types::INTEGER, ['notnull' => false]],
                'client_name' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'client_role' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'client_phone' => [Types::STRING, ['notnull' => false, 'length' => 50]],
                'client_email' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'client_address' => [Types::TEXT, ['notnull' => false]],
                'loc_street' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'loc_city' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'loc_zip' => [Types::STRING, ['notnull' => false, 'length' => 20]],
                'external_ref' => [Types::STRING, ['notnull' => false, 'length' => 255]],
                'date_start' => [Types::DATE, ['notnull' => false]],
                'date_end' => [Types::DATE, ['notnull' => false]],
                'white_board_id' => [Types::STRING, ['notnull' => false, 'length' => 64]],
            ];

            foreach ($columnsToAdd as $columnName => $config) {
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName, $config[0], $config[1]);
                }
            }
        }

        return $schema;
    }
}
