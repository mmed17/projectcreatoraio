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
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Talk\IBroker;
use OCP\Talk\IConversation;
use OCP\Talk\IConversationOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectTalkIntegrationServiceTest extends TestCase
{
    private IBroker&MockObject $talkBroker;
    private IServerContainer&MockObject $serverContainer;
    private IUserManager&MockObject $userManager;
    private IURLGenerator&MockObject $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->talkBroker = $this->createMock(IBroker::class);
        $this->serverContainer = $this->createMock(IServerContainer::class);
        $this->userManager = $this->createMock(IUserManager::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);
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
        );

        $this->assertNull($service->buildConversationUrl(null));
        $this->assertNull($service->buildConversationUrl(''));
    }
}
}
