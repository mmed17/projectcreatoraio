<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010017Date20260309143000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('project_activity_events')) {
			$table = $schema->createTable('project_activity_events');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('project_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('actor_uid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('actor_display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('event_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('payload_json', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('occurred_at', Types::DATETIME, [
				'notnull' => true,
			]);
		} else {
			$table = $schema->getTable('project_activity_events');
		}

		if (!$table->hasPrimaryKey()) {
			$table->setPrimaryKey(['id'], 'pae_pk');
		}
		if (!$table->hasIndex('pae_project_id_idx')) {
			$table->addIndex(['project_id', 'id'], 'pae_project_id_idx');
		}
		if (!$table->hasIndex('pae_occurred_at_idx')) {
			$table->addIndex(['occurred_at'], 'pae_occurred_at_idx');
		}

		if (!$schema->hasTable('project_digest_cursors')) {
			$table = $schema->createTable('project_digest_cursors');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('project_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('user_uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('last_event_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('last_sent_at', Types::DATETIME, [
				'notnull' => false,
			]);
		} else {
			$table = $schema->getTable('project_digest_cursors');
		}

		if (!$table->hasPrimaryKey()) {
			$table->setPrimaryKey(['id'], 'pdc_pk');
		}
		if (!$table->hasIndex('pdc_project_user_uidx')) {
			$table->addUniqueIndex(['project_id', 'user_uid'], 'pdc_project_user_uidx');
		}

		return $schema;
	}
}
