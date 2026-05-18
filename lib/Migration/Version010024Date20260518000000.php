<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010024Date20260518000000 extends SimpleMigrationStep {
	private IDBConnection $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('project_activity_events')) {
			$table = $schema->getTable('project_activity_events');
			if (!$table->hasColumn('source')) {
				$table->addColumn('source', Types::STRING, [
					'notnull' => true,
					'length' => 32,
					'default' => 'internal',
				]);
			}
			if (!$table->hasIndex('pae_project_source_idx')) {
				$table->addIndex(['project_id', 'source', 'occurred_at'], 'pae_project_source_idx');
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('project_activity_events')
			->set('source', $qb->createNamedParameter('whiteboard'))
			->where($qb->expr()->eq('source', $qb->createNamedParameter('internal')))
			->andWhere($qb->expr()->eq('event_type', $qb->createNamedParameter('whiteboard_updated')));
		$qb->executeStatement();
	}
}
