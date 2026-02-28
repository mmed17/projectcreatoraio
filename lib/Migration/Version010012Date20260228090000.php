<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010012Date20260228090000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Keep table name short to satisfy Oracle's 30-char limit (including prefix).
		$newTableName = 'pc_deck_perm_tpl';
		$legacyTableName = 'project_deck_permission_templates';

		if ($schema->hasTable($newTableName) || $schema->hasTable($legacyTableName)) {
			return $schema;
		}

		if (!$schema->hasTable($newTableName)) {
			$table = $schema->createTable($newTableName);
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('organization_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('template_json', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['organization_id'], 'pc_dpt_org_idx');
			$table->addUniqueIndex(['organization_id', 'name'], 'pc_dpt_org_name');
		}

		return $schema;
	}
}
