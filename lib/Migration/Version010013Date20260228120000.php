<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010013Date20260228120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('project_timeline_items')) {
			$table = $schema->getTable('project_timeline_items');

			if (!$table->hasColumn('item_type')) {
				$table->addColumn('item_type', Types::STRING, [
					'notnull' => true,
					'length' => 16,
					'default' => 'phase',
				]);
			}
		}

		return $schema;
	}
}
