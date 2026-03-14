<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010019Date20260313130000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('project_ocr_doc_types')) {
            $table = $schema->createTable('project_ocr_doc_types');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('organization_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('fields_json', Types::TEXT, [
                'notnull' => true,
            ]);
            $table->addColumn('is_active', Types::SMALLINT, [
                'notnull' => true,
                'default' => 1,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['organization_id'], 'podt_org_idx');
            $table->addUniqueIndex(['organization_id', 'name'], 'podt_org_name_uidx');
        }

        if (!$schema->hasTable('project_file_processing')) {
            $table = $schema->createTable('project_file_processing');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('project_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('organization_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('file_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('file_path', Types::STRING, [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('file_name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('mime_type', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('document_type_id', Types::BIGINT, [
                'notnull' => false,
            ]);
            $table->addColumn('ocr_status', Types::STRING, [
                'notnull' => true,
                'length' => 16,
                'default' => 'pending',
            ]);
            $table->addColumn('extracted_json', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('error_message', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('processed_at', Types::DATETIME, [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id'], 'pfp_pk');
            $table->addUniqueIndex(['project_id', 'file_id'], 'pfp_proj_file_uidx');
            $table->addIndex(['organization_id'], 'pfp_org_idx');
        }

        return $schema;
    }
}
