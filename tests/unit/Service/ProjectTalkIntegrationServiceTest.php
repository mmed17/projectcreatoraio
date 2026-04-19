<?php

declare(strict_types=1);

namespace OCA\Talk {
    class Manager
    {
        public function __construct(private readonly object $room)
        {
        }

        public function getRoomByToken(string $token): object
        {
            return $this->room;
        }
    }
}

namespace OCA\Talk\Service {
    use OCP\IUser;

    class ParticipantService
    {
        public array $calls = [];

        public function addUsers(object $room, array $participants, ?IUser $addedBy = null): void
        {
            $this->calls[] = [
                'room' => $room,
                'participants' => $participants,
                'addedBy' => $addedBy,
            ];
        }
    }
}

namespace OCA\ProjectCreatorAIO\Tests\Unit\Service {

use OCA\ProjectCreatorAIO\Service\ProjectTalkIntegrationService;
use OCA\Talk\Manager;
use OCA\Talk\Service\ParticipantService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Talk\IBroker;
use OCP\Talk\IConversation;
use OCP\Talk\IConversationOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectTalkIntegrationServiceTest extends TestCase
{
    private IBroker&MockObject $talkBroker;
    private IServerContainer&MockObject $serverContainer;
    private IUserManager&MockObject $userManager;
    private IURLGenerator&MockObject $urlGenerator;
    private IRootFolder&MockObject $rootFolder;
    private IShareManager&MockObject $shareManager;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->talkBroker = $this->createMock(IBroker::class);
        $this->serverContainer = $this->createMock(IServerContainer::class);
        $this->userManager = $this->createMock(IUserManager::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->shareManager = $this->createMock(IShareManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCreateProjectConversationSeedsNonOwnerMembers(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
            'getDisplayName' => 'Project Owner',
        ]);
        $member = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'member-1',
            'getDisplayName' => 'Member One',
        ]);
        $conversation = $this->createConfiguredMock(IConversation::class, [
            'getId' => 'room-token',
            'getAbsoluteUrl' => 'https://cloud.example.test/call/room-token',
        ]);
        $options = $this->createMock(IConversationOptions::class);

        $room = new \stdClass();
        $participantService = new ParticipantService();
        $manager = new Manager($room);

        $this->talkBroker->expects($this->once())
            ->method('newConversationOptions')
            ->willReturn($options);
        $this->talkBroker->expects($this->once())
            ->method('createConversation')
            ->with('New HQ Build - Chat', [$owner], $options)
            ->willReturn($conversation);

        $this->userManager->expects($this->once())
            ->method('get')
            ->with('member-1')
            ->willReturn($member);

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $result = $service->createProjectConversation('New HQ Build', $owner, ['owner', 'member-1']);

        $this->assertSame('room-token', $result['token']);
        $this->assertSame('https://cloud.example.test/call/room-token', $result['url']);
        $this->assertCount(1, $participantService->calls);
        $this->assertSame($room, $participantService->calls[0]['room']);
        $this->assertSame($owner, $participantService->calls[0]['addedBy']);
        $this->assertSame([[
            'actorType' => 'users',
            'actorId' => 'member-1',
            'displayName' => 'Member One',
            'participantType' => 3,
        ]], $participantService->calls[0]['participants']);
    }

    public function testBuildConversationUrlReturnsNullWithoutToken(): void
    {
        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $this->assertNull($service->buildConversationUrl(null));
        $this->assertNull($service->buildConversationUrl(''));
    }

    public function testShareFileInConversationCreatesRoomShareAndPostsChatMessage(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $room = new \stdClass();
        $manager = new Manager($room);
        $participantService = new ParticipantService();

        $folder = $this->createMock(Folder::class);
        $file = $this->createConfiguredMock(File::class, [
            'getId' => 42,
            'getMimeType' => 'application/octet-stream',
        ]);
        $share = $this->createMock(IShare::class);

        foreach (['setNodeId', 'setShareTime', 'setSharedBy', 'setNode', 'setShareType', 'setSharedWith', 'setPermissions'] as $method) {
            $share->method($method)->willReturnSelf();
        }
        $share->method('getId')->willReturn(321);

        $this->rootFolder->expects($this->once())
            ->method('getUserFolder')
            ->with('owner')
            ->willReturn($folder);
        $folder->expects($this->once())
            ->method('getFirstNodeById')
            ->with(42)
            ->willReturn($file);

        $this->shareManager->expects($this->once())
            ->method('getSharesBy')
            ->with('owner', IShare::TYPE_ROOM, $file, false, -1, 0)
            ->willReturn([]);
        $this->shareManager->expects($this->once())
            ->method('newShare')
            ->willReturn($share);
        $this->shareManager->expects($this->once())
            ->method('createShare')
            ->with($share)
            ->willReturn($share);
        $this->shareManager->expects($this->never())
            ->method('deleteShare');

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $service->shareFileInConversation('room-token', 42, $owner);
    }

    public function testShareFileInConversationSkipsIfShareAlreadyExists(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $room = new \stdClass();
        $manager = new Manager($room);
        $participantService = new ParticipantService();

        $folder = $this->createMock(Folder::class);
        $file = $this->createConfiguredMock(File::class, [
            'getId' => 42,
            'getMimeType' => 'application/octet-stream',
        ]);
        $existingShare = $this->createMock(IShare::class);
        $existingShare->method('getSharedWith')->willReturn('room-token');

        $this->rootFolder->expects($this->once())
            ->method('getUserFolder')
            ->with('owner')
            ->willReturn($folder);
        $folder->expects($this->once())
            ->method('getFirstNodeById')
            ->with(42)
            ->willReturn($file);

        $this->shareManager->expects($this->once())
            ->method('getSharesBy')
            ->with('owner', IShare::TYPE_ROOM, $file, false, -1, 0)
            ->willReturn([$existingShare]);
        $this->shareManager->expects($this->never())->method('newShare');
        $this->shareManager->expects($this->never())->method('createShare');
        $this->shareManager->expects($this->never())->method('deleteShare');

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $created = $service->shareFileInConversation('room-token', 42, $owner);

        $this->assertFalse($created);
    }

    public function testShareFileInConversationPropagatesCreateShareFailure(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $room = new \stdClass();
        $manager = new Manager($room);
        $participantService = new ParticipantService();

        $folder = $this->createMock(Folder::class);
        $file = $this->createConfiguredMock(File::class, [
            'getId' => 42,
            'getMimeType' => 'application/octet-stream',
        ]);
        $share = $this->createMock(IShare::class);

        foreach (['setNodeId', 'setShareTime', 'setSharedBy', 'setNode', 'setShareType', 'setSharedWith', 'setPermissions'] as $method) {
            $share->method($method)->willReturnSelf();
        }
        $share->method('getId')->willReturn(321);

        $this->rootFolder->method('getUserFolder')->with('owner')->willReturn($folder);
        $folder->method('getFirstNodeById')->with(42)->willReturn($file);

        $this->shareManager->method('getSharesBy')
            ->with('owner', IShare::TYPE_ROOM, $file, false, -1, 0)
            ->willReturn([]);
        $this->shareManager->method('newShare')->willReturn($share);
        $this->shareManager->method('createShare')
            ->with($share)
            ->willThrowException(new \RuntimeException('boom'));

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $service->shareFileInConversation('room-token', 42, $owner);
    }

    public function testShareFileInConversationFallsBackToFolderAndSkipsInvalidNames(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $room = new \stdClass();
        $manager = new Manager($room);
        $participantService = new ParticipantService();

        $userFolder = $this->createMock(Folder::class);
        $projectFolder = $this->createMock(Folder::class);
        $invalidFile = $this->createMock(File::class);
        $fallbackFile = $this->createConfiguredMock(File::class, [
            'getId' => 77,
            'getName' => 'Project X.whiteboard',
            'getMimeType' => 'application/octet-stream',
        ]);
        $share = $this->createMock(IShare::class);

        foreach (['setNodeId', 'setShareTime', 'setSharedBy', 'setNode', 'setShareType', 'setSharedWith', 'setPermissions'] as $method) {
            $share->method($method)->willReturnSelf();
        }
        $share->method('getId')->willReturn(654);
        $invalidFile->method('getId')->willReturn(13);
        $invalidFile->method('getName')->willReturn(null);

        $this->rootFolder->expects($this->once())
            ->method('getUserFolder')
            ->with('owner')
            ->willReturn($userFolder);
        $userFolder->expects($this->once())
            ->method('getFirstNodeById')
            ->with(42)
            ->willReturn(null);
        $this->rootFolder->expects($this->exactly(2))
            ->method('getById')
            ->willReturnCallback(static function (int $id) use ($projectFolder): array {
                return match ($id) {
                    42 => [],
                    901 => [$projectFolder],
                    default => [],
                };
            });
        $projectFolder->expects($this->once())
            ->method('getDirectoryListing')
            ->willReturn([$invalidFile, $fallbackFile]);

        $this->shareManager->expects($this->once())
            ->method('getSharesBy')
            ->with('owner', IShare::TYPE_ROOM, $fallbackFile, false, -1, 0)
            ->willReturn([]);
        $this->shareManager->expects($this->once())
            ->method('newShare')
            ->willReturn($share);
        $this->shareManager->expects($this->once())
            ->method('createShare')
            ->with($share)
            ->willReturn($share);

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $created = $service->shareFileInConversation('room-token', 42, $owner, 'Projects/Project X', 'Project X', 99, 901);

        $this->assertTrue($created);
    }

    public function testShareFileInConversationFallsBackToGlobalFileId(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $room = new \stdClass();
        $manager = new Manager($room);
        $participantService = new ParticipantService();

        $userFolder = $this->createMock(Folder::class);
        $globalFile = $this->createConfiguredMock(File::class, [
            'getId' => 42,
            'getMimeType' => 'application/octet-stream',
        ]);
        $share = $this->createMock(IShare::class);

        foreach (['setNodeId', 'setShareTime', 'setSharedBy', 'setNode', 'setShareType', 'setSharedWith', 'setPermissions'] as $method) {
            $share->method($method)->willReturnSelf();
        }
        $share->method('getId')->willReturn(777);

        $this->rootFolder->expects($this->once())
            ->method('getUserFolder')
            ->with('owner')
            ->willReturn($userFolder);
        $userFolder->expects($this->once())
            ->method('getFirstNodeById')
            ->with(42)
            ->willReturn(null);
        $this->rootFolder->expects($this->once())
            ->method('getById')
            ->with(42)
            ->willReturn([$globalFile]);

        $this->shareManager->expects($this->once())
            ->method('getSharesBy')
            ->with('owner', IShare::TYPE_ROOM, $globalFile, false, -1, 0)
            ->willReturn([]);
        $this->shareManager->expects($this->once())
            ->method('newShare')
            ->willReturn($share);
        $this->shareManager->expects($this->once())
            ->method('createShare')
            ->with($share)
            ->willReturn($share);

        $this->serverContainer->method('get')
            ->willReturnCallback(static function (string $serviceClass) use ($manager, $participantService): object {
                return match ($serviceClass) {
                    'OCA\\Talk\\Manager' => $manager,
                    'OCA\\Talk\\Service\\ParticipantService' => $participantService,
                    default => throw new \RuntimeException('Unexpected service lookup'),
                };
            });

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $created = $service->shareFileInConversation('room-token', 42, $owner, 'Projects/Project X', 'Project X', 99, 901);

        $this->assertTrue($created);
    }

    public function testShareFileInConversationFailsWhenNoFileResolutionPathWorks(): void
    {
        $owner = $this->createConfiguredMock(IUser::class, [
            'getUID' => 'owner',
        ]);
        $userFolder = $this->createMock(Folder::class);

        $this->rootFolder->expects($this->once())
            ->method('getUserFolder')
            ->with('owner')
            ->willReturn($userFolder);
        $userFolder->expects($this->once())
            ->method('getFirstNodeById')
            ->with(42)
            ->willReturn(null);
        $this->rootFolder->expects($this->exactly(2))
            ->method('getById')
            ->willReturn([]);
        $userFolder->expects($this->once())
            ->method('get')
            ->with('Projects/Project X')
            ->willThrowException(new \OCP\Files\NotFoundException());

        $service = new ProjectTalkIntegrationService(
            $this->talkBroker,
            $this->serverContainer,
            $this->userManager,
            $this->urlGenerator,
            $this->rootFolder,
            $this->shareManager,
            $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File 42 is not accessible for user "owner".');

        $service->shareFileInConversation('room-token', 42, $owner, 'Projects/Project X', 'Project X', 99, 901);
    }
}
}
