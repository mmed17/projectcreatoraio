<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service;

use DateTime;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectActivityEvent;
use OCA\ProjectCreatorAIO\Db\ProjectActivityEventMapper;
use OCA\ProjectCreatorAIO\Db\ProjectDigestCursorMapper;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectActivityService;
use OCA\ProjectCreatorAIO\Service\ProjectDigestService;
use OCA\ProjectCreatorAIO\Service\ProjectMemberResolver;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectDigestServiceTest extends TestCase {
	public function testSendDailyDigestsSendsEmailAndAdvancesCursor(): void {
		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');

		$event = new ProjectActivityEvent();
		$event->setId(9);
		$event->setEventType(ProjectActivityService::EVENT_NOTE_CREATED);
		$event->setActorUid('owner1');
		$event->setActorDisplayName('Owner One');
		$event->setPayloadArray([
			'title' => 'Weekly recap',
			'visibility' => 'public',
		]);
		$event->setOccurredAt(new DateTime('2026-03-09 09:00:00'));

		$recipient = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member1',
			'getDisplayName' => 'Member One',
			'getEMailAddress' => 'member1@example.com',
		]);

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->expects($this->once())->method('list')->willReturn([$project]);

		$eventMapper = $this->createMock(ProjectActivityEventMapper::class);
		$eventMapper->expects($this->once())
			->method('findForDigest')
			->with(42, 0, $this->isInstanceOf(DateTime::class))
			->willReturn([$event]);

		$cursorMapper = $this->createMock(ProjectDigestCursorMapper::class);
		$cursorMapper->expects($this->once())
			->method('findByProjectAndUser')
			->with(42, 'member1')
			->willReturn(null);
		$cursorMapper->expects($this->once())
			->method('advanceCursor')
			->with(42, 'member1', 9, $this->isInstanceOf(DateTime::class));

		$memberResolver = $this->createMock(ProjectMemberResolver::class);
		$memberResolver->expects($this->once())->method('getProjectMembers')->with($project)->willReturn([$recipient]);

		$template = $this->createMock(IEMailTemplate::class);
		$template->expects($this->once())->method('setSubject');
		$template->expects($this->once())->method('addHeader');
		$template->expects($this->once())->method('addHeading');
		$template->expects($this->once())->method('addBodyText');
		$template->expects($this->once())->method('addBodyButton');
		$template->expects($this->once())->method('addFooter');
		$template->expects($this->once())
			->method('addBodyListItem')
			->with(
				'Owner One created a public note "Weekly recap"',
				'2026-03-09 09:00',
				'',
				'Owner One created a public note "Weekly recap"',
				'2026-03-09 09:00'
			);

		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->method('setAutoSubmitted')->willReturnSelf();
		$message->method('useTemplate')->willReturnSelf();

		$mailer = $this->createMock(IMailer::class);
		$mailer->expects($this->once())->method('createEMailTemplate')->willReturn($template);
		$mailer->expects($this->once())->method('createMessage')->willReturn($message);
		$mailer->expects($this->once())->method('send')->with($message)->willReturn([]);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->with('projectcreatoraio.page.index')->willReturn('https://cloud.example/apps/projectcreatoraio');

		$service = new ProjectDigestService(
			$projectMapper,
			$eventMapper,
			$cursorMapper,
			$memberResolver,
			$mailer,
			$urlGenerator,
			$this->createMock(LoggerInterface::class),
		);

		$service->sendDailyDigests(new DateTime('2026-03-09 12:00:00'));
	}
}
