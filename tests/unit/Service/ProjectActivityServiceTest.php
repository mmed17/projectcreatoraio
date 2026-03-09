<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service;

use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectActivityEventMapper;
use OCA\ProjectCreatorAIO\Db\ProjectNote;
use OCA\ProjectCreatorAIO\Service\ProjectActivityService;
use OCP\IUser;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectActivityServiceTest extends TestCase {
	public function testRecordNoteCreatedStoresNormalizedPayload(): void {
		$eventMapper = $this->createMock(ProjectActivityEventMapper::class);
		$eventMapper->expects($this->once())
			->method('createEvent')
			->with(
				42,
				ProjectActivityService::EVENT_NOTE_CREATED,
				'owner1',
				'Owner One',
				[
					'noteId' => 7,
					'title' => 'Weekly recap',
					'visibility' => 'public',
					'projectName' => 'Alpha',
				],
			);

		$service = new ProjectActivityService($eventMapper, $this->createMock(LoggerInterface::class));

		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');

		$note = new ProjectNote();
		$note->setId(7);
		$note->setTitle('Weekly recap');
		$note->setVisibility('public');

		$actor = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'owner1',
			'getDisplayName' => 'Owner One',
		]);

		$service->recordNoteCreated($project, $note, $actor);
	}
}
