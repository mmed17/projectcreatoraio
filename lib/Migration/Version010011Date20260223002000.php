<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010011Date20260223002000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('custom_projects')) {
			return null;
		}

		$table = $schema->getTable('custom_projects');

		if (!$table->hasColumn('cv_object_ownership')) {
			$table->addColumn('cv_object_ownership', Types::SMALLINT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		if (!$table->hasColumn('cv_trace_ownership')) {
			$table->addColumn('cv_trace_ownership', Types::SMALLINT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		if (!$table->hasColumn('cv_building_type')) {
			$table->addColumn('cv_building_type', Types::SMALLINT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		if (!$table->hasColumn('cv_avp_location')) {
			$table->addColumn('cv_avp_location', Types::SMALLINT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}
