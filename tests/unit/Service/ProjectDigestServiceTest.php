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
	public function testSendDailyDigestsSendsSingleProjectEmailAndAdvancesCursor(): void {
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

	public function testSendDailyDigestsSendsOneEmailPerUserGroupedByProject(): void {
		$alpha = new Project();
		$alpha->setId(42);
		$alpha->setName('Alpha');

		$beta = new Project();
		$beta->setId(43);
		$beta->setName('Beta');

		$alphaEvent = new ProjectActivityEvent();
		$alphaEvent->setId(9);
		$alphaEvent->setEventType(ProjectActivityService::EVENT_NOTE_CREATED);
		$alphaEvent->setActorUid('owner1');
		$alphaEvent->setActorDisplayName('Owner One');
		$alphaEvent->setPayloadArray([
			'title' => 'Weekly recap',
			'visibility' => 'public',
		]);
		$alphaEvent->setOccurredAt(new DateTime('2026-03-09 09:00:00'));

		$betaEvent = new ProjectActivityEvent();
		$betaEvent->setId(15);
		$betaEvent->setEventType(ProjectActivityService::EVENT_TIMELINE_ITEM_UPDATED);
		$betaEvent->setActorUid('owner2');
		$betaEvent->setActorDisplayName('Owner Two');
		$betaEvent->setPayloadArray([
			'itemType' => 'milestone',
			'label' => 'Go live',
		]);
		$betaEvent->setOccurredAt(new DateTime('2026-03-09 10:30:00'));

		$recipient = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member1',
			'getDisplayName' => 'Member One',
			'getEMailAddress' => 'member1@example.com',
		]);

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->expects($this->once())->method('list')->willReturn([$alpha, $beta]);

		$eventMapper = $this->createMock(ProjectActivityEventMapper::class);
		$eventMapper->expects($this->exactly(2))
			->method('findForDigest')
			->willReturnCallback(function (int $projectId) use ($alphaEvent, $betaEvent): array {
				return match ($projectId) {
					42 => [$alphaEvent],
					43 => [$betaEvent],
					default => [],
				};
			});

		$cursorMapper = $this->createMock(ProjectDigestCursorMapper::class);
		$cursorMapper->expects($this->exactly(2))
			->method('findByProjectAndUser')
			->withAnyParameters()
			->willReturn(null);
		$cursorMapper->expects($this->exactly(2))
			->method('advanceCursor')
			->willReturnCallback(function (int $projectId, string $userUid, int $lastEventId): void {
				TestCase::assertSame('member1', $userUid);
				if ($projectId === 42) {
					TestCase::assertSame(9, $lastEventId);
					return;
				}

				TestCase::assertSame(43, $projectId);
				TestCase::assertSame(15, $lastEventId);
			});

		$memberResolver = $this->createMock(ProjectMemberResolver::class);
		$memberResolver->expects($this->exactly(2))
			->method('getProjectMembers')
			->willReturn([$recipient]);

		$template = $this->createMock(IEMailTemplate::class);
		$template->expects($this->once())->method('setSubject')->with('Daily project activity summary (2)');
		$template->expects($this->once())->method('addHeader');
		$template->expects($this->exactly(3))->method('addHeading');
		$template->expects($this->once())->method('addBodyText')->with('Here is the latest activity across your projects.');
		$template->expects($this->once())->method('addBodyButton');
		$template->expects($this->once())->method('addFooter');
		$template->expects($this->exactly(2))
			->method('addBodyListItem')
			->withConsecutive(
				[
					'Owner One created a public note "Weekly recap"',
					'2026-03-09 09:00',
					'',
					'Owner One created a public note "Weekly recap"',
					'2026-03-09 09:00',
				],
				[
					'Owner Two updated timeline milestone "Go live"',
					'2026-03-09 10:30',
					'',
					'Owner Two updated timeline milestone "Go live"',
					'2026-03-09 10:30',
				],
			);

		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())
			->method('setTo')
			->with(['member1@example.com' => 'Member One'])
			->willReturnSelf();
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
