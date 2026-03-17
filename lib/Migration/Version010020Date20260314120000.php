<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010020Date20260314120000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $db,
    ) {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('project_ocr_doc_types')) {
            $table = $schema->getTable('project_ocr_doc_types');
            if (!$table->hasColumn('name')) {
                $table->addColumn('name', Types::STRING, [
                    'notnull' => false,
                    'length' => 255,
                ]);
            }
            if (!$table->hasIndex('podt_org_name_idx')) {
                $table->addIndex(['organization_id', 'name'], 'podt_org_name_idx');
            }
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('project_ocr_doc_types')) {
            return;
        }
        $table = $schema->getTable('project_ocr_doc_types');
        $hasLabelColumn = $table->hasColumn('label');
        $hasTypeKeyColumn = $table->hasColumn('type_key');

        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'name', 'fields_json')
            ->from('project_ocr_doc_types');
        if ($hasLabelColumn) {
            $qb->addSelect('label');
        }
        if ($hasTypeKeyColumn) {
            $qb->addSelect('type_key');
        }
        $result = $qb->executeQuery();

        while (($row = $result->fetch()) !== false) {
            $name = $this->normalizeDocumentTypeName($row);
            $fieldsJson = json_encode($this->normalizeFieldsJson((string) ($row['fields_json'] ?? '')), JSON_UNESCAPED_SLASHES);

            $update = $this->db->getQueryBuilder();
            $update->update('project_ocr_doc_types')
                ->set('name', $update->createNamedParameter($name))
                ->set('fields_json', $update->createNamedParameter($fieldsJson))
                ->where($update->expr()->eq('id', $update->createNamedParameter((int) $row['id'])));
            $update->executeStatement();
        }

        $result->closeCursor();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeDocumentTypeName(array $row): string
    {
        $candidates = [
            trim((string) ($row['name'] ?? '')),
            trim((string) ($row['label'] ?? '')),
            trim((string) ($row['type_key'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return substr($candidate, 0, 255);
            }
        }

        return 'Untitled document type';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function normalizeFieldsJson(string $fieldsJson): array
    {
        $decoded = json_decode($fieldsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalizedFields = [];
        $seen = [];

        foreach ($decoded as $field) {
            $name = '';
            if (is_string($field)) {
                $name = trim($field);
            } elseif (is_array($field)) {
                $name = trim((string) ($field['name'] ?? $field['label'] ?? $field['key'] ?? ''));
            }

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $normalizedFields[] = ['name' => substr($name, 0, 255)];
        }

        return $normalizedFields;
    }
}
