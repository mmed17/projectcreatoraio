<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010016Date20260306170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('custom_projects')) {
			return $schema;
		}

		$table = $schema->getTable('custom_projects');
		if (!$table->hasColumn('last_deck_move_at')) {
			$table->addColumn('last_deck_move_at', Types::DATETIME, [
				'notnull' => false,
			]);
		}
		if (!$table->hasColumn('stale_notified_at')) {
			$table->addColumn('stale_notified_at', Types::DATETIME, [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
