<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010014Date20260228121000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$legacyTableName = 'project_deck_permission_templates';
		$newTableName = 'pc_deck_perm_tpl';

		if ($schema->hasTable($legacyTableName) && !$schema->hasTable($newTableName)) {
			$schema->renameTable($legacyTableName, $newTableName);
		}

		return $schema;
	}
}
