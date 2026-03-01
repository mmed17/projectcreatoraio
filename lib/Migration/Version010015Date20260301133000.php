<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010015Date20260301133000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Templates are now owned by the Deck app
		if ($schema->hasTable('pc_deck_perm_tpl')) {
			$schema->dropTable('pc_deck_perm_tpl');
		}
		if ($schema->hasTable('project_deck_permission_templates')) {
			$schema->dropTable('project_deck_permission_templates');
		}

		return $schema;
	}
}
