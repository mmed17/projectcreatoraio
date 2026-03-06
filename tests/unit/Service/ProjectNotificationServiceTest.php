<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service;

use OCA\ProjectCreatorAIO\AppInfo\Application;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Service\ProjectNotificationService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectNotificationServiceTest extends TestCase {
	public function testNotifyMemberAddedPublishesNotification(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();

		$notificationManager = $this->createMock(INotificationManager::class);
		$notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$notificationManager->expects($this->once())
			->method('notify')
			->with($notification);
		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('isLocalCacheAvailable')->willReturn(false);
		$cacheFactory->method('isAvailable')->willReturn(false);
		$logger = $this->createMock(LoggerInterface::class);

		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');

		$member = $this->createMock(IUser::class);
		$member->method('getUID')->willReturn('new-member');

		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('owner1');
		$actor->method('getDisplayName')->willReturn('Owner One');

		$notification->expects($this->once())
			->method('setApp')
			->with(Application::APP_ID)
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setUser')
			->with('new-member')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setObject')
			->with('project_member', '42:new-member')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setSubject')
			->with(
				ProjectNotificationService::SUBJECT_MEMBER_ADDED,
				[
					'projectId' => '42',
					'projectName' => 'Alpha',
					'actorUid' => 'owner1',
					'actorDisplayName' => 'Owner One',
				],
			)
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setDateTime')
			->with($this->isInstanceOf(\DateTime::class))
			->willReturnSelf();

		$service = new ProjectNotificationService($notificationManager, $groupManager, $userManager, $cacheFactory, $logger);
		$service->notifyMemberAdded($project, $member, $actor);
	}

	public function testNotifyWhiteboardUpdatedPublishesForOtherMembersOnly(): void {
		$notifications = [];

		$notificationManager = $this->createMock(INotificationManager::class);
		$notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->willReturnCallback(function () use (&$notifications) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setUser')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notifications[] = $notification;
				return $notification;
			});
		$notificationManager->expects($this->exactly(2))
			->method('notify');

		$groupMember = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member-2',
		]);
		$owner = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'owner1',
		]);
		$actor = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member-1',
			'getDisplayName' => 'Member One',
		]);

		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([
			$actor,
			$groupMember,
		]);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')
			->with('project-alpha')
			->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->with('owner1')
			->willReturn($owner);

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->with('whiteboard:42')->willReturn(null);
		$cache->expects($this->once())
			->method('set')
			->with('whiteboard:42', $this->isType('int'), 120)
			->willReturn(true);

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('isLocalCacheAvailable')->willReturn(true);
		$cacheFactory->method('createLocal')->willReturn($cache);

		$logger = $this->createMock(LoggerInterface::class);

		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');
		$project->setOwnerId('owner1');
		$project->setProjectGroupGid('project-alpha');

		$notifications[0] = null;
		$notifications[1] = null;

		$service = new ProjectNotificationService($notificationManager, $groupManager, $userManager, $cacheFactory, $logger);
		$service->notifyWhiteboardUpdated($project, $actor);
	}

	public function testNotifyWhiteboardUpdatedRespectsCooldown(): void {
		$notificationManager = $this->createMock(INotificationManager::class);
		$notificationManager->expects($this->never())->method('createNotification');
		$notificationManager->expects($this->never())->method('notify');

		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->with('whiteboard:42')->willReturn(time());
		$cache->expects($this->never())->method('set');

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('isLocalCacheAvailable')->willReturn(true);
		$cacheFactory->method('createLocal')->willReturn($cache);

		$logger = $this->createMock(LoggerInterface::class);

		$project = new Project();
		$project->setId(42);
		$project->setProjectGroupGid('project-alpha');

		$actor = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member-1',
			'getDisplayName' => 'Member One',
		]);

		$service = new ProjectNotificationService($notificationManager, $groupManager, $userManager, $cacheFactory, $logger);
		$service->notifyWhiteboardUpdated($project, $actor);
	}

	public function testNotifyDeckStalePublishesToAllMembers(): void {
		$notificationManager = $this->createMock(INotificationManager::class);
		$notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->willReturnCallback(function () {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setUser')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				return $notification;
			});
		$notificationManager->expects($this->exactly(2))->method('notify');

		$groupMember = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'member-1',
		]);
		$owner = $this->createConfiguredMock(IUser::class, [
			'getUID' => 'owner1',
		]);

		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$groupMember]);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('project-alpha')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('owner1')->willReturn($owner);

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('isLocalCacheAvailable')->willReturn(false);
		$cacheFactory->method('isAvailable')->willReturn(false);

		$logger = $this->createMock(LoggerInterface::class);

		$project = new Project();
		$project->setId(42);
		$project->setName('Alpha');
		$project->setOwnerId('owner1');
		$project->setProjectGroupGid('project-alpha');

		$service = new ProjectNotificationService($notificationManager, $groupManager, $userManager, $cacheFactory, $logger);
		$service->notifyDeckStale($project);
	}
}
