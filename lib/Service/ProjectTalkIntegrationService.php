<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use OCP\IUser;
use OCP\IUserManager;
use OCP\IServerContainer;
use OCP\Talk\IBroker;
use OCP\Talk\Exceptions\NoBackendException;
use OCP\IURLGenerator;
use RuntimeException;
use Throwable;

class ProjectTalkIntegrationService
{
    private const SPREED_MANAGER_CLASS = 'OCA\\Talk\\Manager';
    private const SPREED_PARTICIPANT_SERVICE_CLASS = 'OCA\\Talk\\Service\\ParticipantService';
    private const TALK_ACTOR_USERS = 'users';
    private const TALK_PARTICIPANT_USER = 3;

    public function __construct(
        private readonly IBroker $talkBroker,
        private readonly IServerContainer $serverContainer,
        private readonly IUserManager $userManager,
        private readonly IURLGenerator $urlGenerator,
    ) {
    }

    public function isAvailable(): bool
    {
        try {
            return $this->talkBroker->hasBackend();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param string[] $memberIds
     * @return array{token: string, url: string}
     */
    public function createProjectConversation(string $projectName, IUser $owner, array $memberIds): array
    {
        try {
            $conversation = $this->talkBroker->createConversation(
                trim($projectName) . ' - Chat',
                [$owner],
                $this->talkBroker->newConversationOptions(),
            );
        } catch (NoBackendException $e) {
            throw new RuntimeException('Talk is not available.', 0, $e);
        }

        $token = $conversation->getId();
        $otherMemberIds = array_values(array_filter(
            array_unique(array_map('strval', $memberIds)),
            static fn (string $memberId): bool => $memberId !== '' && $memberId !== $owner->getUID(),
        ));

        try {
            if ($otherMemberIds !== []) {
                $room = $this->getTalkManager()->getRoomByToken($token);
                $participants = [];
                foreach ($otherMemberIds as $memberId) {
                    $user = $this->userManager->get($memberId);
                    if ($user === null) {
                        continue;
                    }

                    $participants[] = [
                        'actorType' => self::TALK_ACTOR_USERS,
                        'actorId' => $user->getUID(),
                        'displayName' => $user->getDisplayName(),
                        'participantType' => self::TALK_PARTICIPANT_USER,
                    ];
                }

                $this->getParticipantService()->addUsers($room, $participants, $owner);
            }
        } catch (Throwable $e) {
            try {
                $this->talkBroker->deleteConversation($token);
            } catch (Throwable) {
            }

            throw $e;
        }

        return [
            'token' => $token,
            'url' => $conversation->getAbsoluteUrl(),
        ];
    }

    public function addUserToConversation(string $conversationToken, IUser $user, ?IUser $addedBy = null): void
    {
        $conversationToken = trim($conversationToken);
        if ($conversationToken === '') {
            return;
        }

        $room = $this->getTalkManager()->getRoomByToken($conversationToken);
        $participants = [[
            'actorType' => self::TALK_ACTOR_USERS,
            'actorId' => $user->getUID(),
            'displayName' => $user->getDisplayName(),
            'participantType' => self::TALK_PARTICIPANT_USER,
        ]];

        $this->getParticipantService()->addUsers($room, $participants, $addedBy);
    }

    public function deleteConversation(string $conversationToken): void
    {
        $conversationToken = trim($conversationToken);
        if ($conversationToken === '') {
            return;
        }

        try {
            $this->talkBroker->deleteConversation($conversationToken);
        } catch (NoBackendException) {
        }
    }

    public function buildConversationUrl(?string $conversationToken): ?string
    {
        $conversationToken = trim((string) $conversationToken);
        if ($conversationToken === '') {
            return null;
        }

        return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $conversationToken]);
    }

    private function getTalkManager(): object
    {
        return $this->resolveTalkService(self::SPREED_MANAGER_CLASS);
    }

    private function getParticipantService(): object
    {
        return $this->resolveTalkService(self::SPREED_PARTICIPANT_SERVICE_CLASS);
    }

    private function resolveTalkService(string $serviceClass): object
    {
        if (!class_exists($serviceClass)) {
            throw new RuntimeException(sprintf('Talk service "%s" is not available.', $serviceClass));
        }

        return $this->serverContainer->get($serviceClass);
    }
}
