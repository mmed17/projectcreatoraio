<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010009Date20260223000000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('custom_projects')) {
			return null;
		}

		$table = $schema->getTable('custom_projects');
		if ($table->hasColumn('required_preparation_weeks')) {
			return null;
		}

		$table->addColumn('required_preparation_weeks', Types::INTEGER, [
			'notnull' => false,
			'default' => 0,
		]);

		return $schema;
	}
}

