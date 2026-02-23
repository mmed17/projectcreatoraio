<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010010Date20260223001000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('project_deck_done_sync')) {
			return null;
		}

		$table = $schema->createTable('project_deck_done_sync');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('card_id', Types::BIGINT, [
			'notnull' => true,
		]);
		// Use SMALLINT instead of BOOLEAN for cross-DB compatibility (some platforms
		// don't accept DEFAULT false on NOT NULL boolean columns).
		$table->addColumn('managed_done', Types::SMALLINT, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
		]);
		$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['project_id', 'card_id'], 'pdds_proj_card_uniq');
		$table->addIndex(['project_id'], 'pdds_project_idx');
		$table->addIndex(['card_id'], 'pdds_card_idx');

		return $schema;
	}
}
