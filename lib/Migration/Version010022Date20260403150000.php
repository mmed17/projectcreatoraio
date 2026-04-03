<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010022Date20260403150000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('project_file_processing')) {
			$table = $schema->getTable('project_file_processing');
			if (!$table->hasColumn('storage_scope')) {
				$table->addColumn('storage_scope', Types::STRING, [
					'notnull' => true,
					'length' => 16,
					'default' => 'shared',
				]);
			}
		}

		return $schema;
	}
}
