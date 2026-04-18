<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IServerContainer;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Talk\IBroker;
use OCP\Talk\Exceptions\NoBackendException;
use OCP\IURLGenerator;
use RuntimeException;
use Throwable;

class ProjectTalkIntegrationService
{
    private const SPREED_MANAGER_CLASS = 'OCA\\Talk\\Manager';
    private const SPREED_PARTICIPANT_SERVICE_CLASS = 'OCA\\Talk\\Service\\ParticipantService';
    private const SPREED_CHAT_MANAGER_CLASS = 'OCA\\Talk\\Chat\\ChatManager';
    private const TALK_ACTOR_USERS = 'users';
    private const TALK_PARTICIPANT_USER = 3;

    public function __construct(
        private readonly IBroker $talkBroker,
        private readonly IServerContainer $serverContainer,
        private readonly IUserManager $userManager,
        private readonly IURLGenerator $urlGenerator,
        private readonly IRootFolder $rootFolder,
        private readonly IShareManager $shareManager,
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

    public function shareFileInConversation(string $conversationToken, int $fileId, IUser $actor): bool
    {
        $conversationToken = trim($conversationToken);
        if ($conversationToken === '' || $fileId <= 0) {
            return false;
        }

        $room = $this->getTalkManager()->getRoomByToken($conversationToken);
        $participant = $this->getParticipantService()->getParticipant($room, $actor->getUID(), false);
        $file = $this->resolveUserFile($actor, $fileId);
        if ($this->hasRoomShare($conversationToken, $file, $actor)) {
            return false;
        }

        $share = $this->createRoomShare($conversationToken, $file, $actor);

        try {
            $message = json_encode([
                'message' => 'file_shared',
                'parameters' => [
                    'share' => $share->getId(),
                    'metaData' => [
                        'mimeType' => $file->getMimeType(),
                    ],
                ],
            ], JSON_THROW_ON_ERROR);

            $this->getChatManager()->addSystemMessage(
                $room,
                $participant,
                self::TALK_ACTOR_USERS,
                $actor->getUID(),
                $message,
                new DateTime(),
                true,
            );
            return true;
        } catch (Throwable $e) {
            $this->shareManager->deleteShare($share);
            throw $e;
        }
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

    private function getChatManager(): object
    {
        return $this->resolveTalkService(self::SPREED_CHAT_MANAGER_CLASS);
    }

    private function resolveUserFile(IUser $actor, int $fileId): File
    {
        $userFolder = $this->rootFolder->getUserFolder($actor->getUID());
        $node = $userFolder->getFirstNodeById($fileId);
        if (!$node instanceof File) {
            throw new RuntimeException(sprintf('File %d is not accessible for user "%s".', $fileId, $actor->getUID()));
        }

        return $node;
    }

    private function createRoomShare(string $conversationToken, File $file, IUser $actor): IShare
    {
        $share = $this->shareManager->newShare();
        $share->setNodeId($file->getId())
            ->setShareTime(new DateTime())
            ->setSharedBy($actor->getUID())
            ->setNode($file)
            ->setShareType(IShare::TYPE_ROOM)
            ->setSharedWith($conversationToken)
            ->setPermissions(Constants::PERMISSION_READ);

        return $this->shareManager->createShare($share);
    }

    private function hasRoomShare(string $conversationToken, File $file, IUser $actor): bool
    {
        $shares = $this->shareManager->getSharesBy(
            $actor->getUID(),
            IShare::TYPE_ROOM,
            $file,
            false,
            -1,
            0,
        );

        foreach ($shares as $share) {
            if ($share->getSharedWith() === $conversationToken) {
                return true;
            }
        }

        return false;
    }

    private function resolveTalkService(string $serviceClass): object
    {
        if (!class_exists($serviceClass)) {
            throw new RuntimeException(sprintf('Talk service "%s" is not available.', $serviceClass));
        }

        return $this->serverContainer->get($serviceClass);
    }
}
