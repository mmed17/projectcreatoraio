<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010021Date20260314123000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('project_ocr_doc_types')) {
            $table = $schema->getTable('project_ocr_doc_types');

            if ($table->hasIndex('podt_org_key_uidx')) {
                $table->dropIndex('podt_org_key_uidx');
            }
            if ($table->hasIndex('podt_org_name_idx')) {
                $table->dropIndex('podt_org_name_idx');
            }
            if (!$table->hasIndex('podt_org_name_uidx')) {
                $table->addUniqueIndex(['organization_id', 'name'], 'podt_org_name_uidx');
            }
            if ($table->hasColumn('type_key')) {
                $table->dropColumn('type_key');
            }
            if ($table->hasColumn('label')) {
                $table->dropColumn('label');
            }
        }

        if ($schema->hasTable('project_file_processing')) {
            $table = $schema->getTable('project_file_processing');

            if ($table->hasIndex('pfp_type_key_idx')) {
                $table->dropIndex('pfp_type_key_idx');
            }
            if ($table->hasColumn('document_type_key')) {
                $table->dropColumn('document_type_key');
            }
        }

        return $schema;
    }
}
