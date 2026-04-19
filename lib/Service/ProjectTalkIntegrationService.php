<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IServerContainer;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Talk\IBroker;
use OCP\Talk\Exceptions\NoBackendException;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
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

    public function shareFileInConversation(
        string $conversationToken,
        int $fileId,
        IUser $actor,
        ?string $projectFolderPath = null,
        ?string $projectName = null,
        ?int $projectId = null,
        ?int $projectFolderId = null,
    ): bool
    {
        $conversationToken = trim($conversationToken);
        if ($conversationToken === '' || $fileId <= 0) {
            $this->logger->debug('Skipping Talk file share because payload is incomplete', [
                'projectId' => $projectId,
                'conversationToken' => $conversationToken,
                'fileId' => $fileId,
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
            ]);
            return false;
        }

        $this->logger->debug('Starting Talk file share for project whiteboard', [
            'projectId' => $projectId,
            'conversationToken' => $conversationToken,
            'fileId' => $fileId,
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
            'projectFolderPath' => $projectFolderPath,
            'projectName' => $projectName,
        ]);

        $room = $this->getTalkManager()->getRoomByToken($conversationToken);
        $participant = $this->getParticipantService()->getParticipant($room, $actor->getUID(), false);
        $file = $this->resolveUserFile($actor, $fileId, $projectFolderPath, $projectName, $projectId, $projectFolderId);
        if ($this->hasRoomShare($conversationToken, $file, $actor)) {
            $this->logger->debug('Skipping Talk file share because room share already exists', [
                'projectId' => $projectId,
                'conversationToken' => $conversationToken,
                'fileId' => $fileId,
                'resolvedFileId' => $file->getId(),
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
            ]);
            return false;
        }

        $share = $this->createRoomShare($conversationToken, $file, $actor);
        $this->logger->debug('Created Talk room share for project whiteboard', [
            'projectId' => $projectId,
            'conversationToken' => $conversationToken,
            'fileId' => $fileId,
            'resolvedFileId' => $file->getId(),
            'shareId' => $share->getId(),
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
        ]);

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
            $this->logger->debug('Posted Talk system message for project whiteboard share', [
                'projectId' => $projectId,
                'conversationToken' => $conversationToken,
                'fileId' => $fileId,
                'resolvedFileId' => $file->getId(),
                'shareId' => $share->getId(),
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->shareManager->deleteShare($share);
            $this->logger->warning('Failed to post Talk system message for project whiteboard share', [
                'projectId' => $projectId,
                'conversationToken' => $conversationToken,
                'fileId' => $fileId,
                'resolvedFileId' => $file->getId(),
                'shareId' => $share->getId(),
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
                'exception' => $e,
            ]);
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

    private function resolveUserFile(
        IUser $actor,
        int $fileId,
        ?string $projectFolderPath = null,
        ?string $projectName = null,
        ?int $projectId = null,
        ?int $projectFolderId = null,
    ): File
    {
        $userFolder = $this->rootFolder->getUserFolder($actor->getUID());
        $node = $userFolder->getFirstNodeById($fileId);
        if ($node instanceof File) {
            $this->logger->debug('Resolved project whiteboard directly by file id', [
                'projectId' => $projectId,
                'fileId' => $fileId,
                'resolvedFileId' => $node->getId(),
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
            ]);
            return $node;
        }

        $this->logger->debug('Direct project whiteboard lookup by user-scoped file id failed, trying global file id fallback', [
            'projectId' => $projectId,
            'fileId' => $fileId,
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
            'projectFolderPath' => $projectFolderPath,
            'projectName' => $projectName,
        ]);

        $globalFile = $this->resolveGlobalFileById($fileId);
        if ($globalFile instanceof File) {
            $this->logger->debug('Resolved project whiteboard by global file id fallback', [
                'projectId' => $projectId,
                'fileId' => $fileId,
                'resolvedFileId' => $globalFile->getId(),
                'actorUserId' => $actor->getUID(),
                'projectFolderId' => $projectFolderId,
            ]);
            return $globalFile;
        }

        $this->logger->debug('Global file id fallback failed, trying shared folder id fallback', [
            'projectId' => $projectId,
            'fileId' => $fileId,
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
            'projectFolderPath' => $projectFolderPath,
            'projectName' => $projectName,
        ]);

        if (($projectFolderId ?? 0) > 0) {
            $projectFolder = $this->resolveGlobalFolderById((int) $projectFolderId);
            if ($projectFolder instanceof Folder) {
                $fallbackFile = $this->findWhiteboardInFolder(
                    $projectFolder,
                    trim((string) $projectName),
                    $projectId,
                    $actor->getUID(),
                );
                if ($fallbackFile instanceof File) {
                    return $fallbackFile;
                }
            }
        }

        $this->logger->debug('Shared folder id fallback failed, trying user path fallback for compatibility', [
            'projectId' => $projectId,
            'fileId' => $fileId,
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
            'projectFolderPath' => $projectFolderPath,
            'projectName' => $projectName,
        ]);

        $folderPath = trim((string) $projectFolderPath);
        if ($folderPath !== '') {
            try {
                $folderNode = $userFolder->get($folderPath);
                if ($folderNode instanceof Folder) {
                    $fallbackFile = $this->findWhiteboardInFolder(
                        $folderNode,
                        trim((string) $projectName),
                        $projectId,
                        $actor->getUID(),
                    );
                    if ($fallbackFile instanceof File) {
                        return $fallbackFile;
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('Project whiteboard folder fallback lookup failed', [
                    'projectId' => $projectId,
                    'fileId' => $fileId,
                    'actorUserId' => $actor->getUID(),
                    'projectFolderId' => $projectFolderId,
                    'projectFolderPath' => $folderPath,
                    'projectName' => $projectName,
                    'exception' => $e,
                ]);
            }
        }

        $this->logger->warning('Project whiteboard could not be resolved for Talk sharing', [
            'projectId' => $projectId,
            'fileId' => $fileId,
            'actorUserId' => $actor->getUID(),
            'projectFolderId' => $projectFolderId,
            'projectFolderPath' => $folderPath,
            'projectName' => $projectName,
        ]);
        throw new RuntimeException(sprintf('File %d is not accessible for user "%s".', $fileId, $actor->getUID()));
    }

    private function resolveGlobalFileById(int $fileId): ?File
    {
        foreach ($this->rootFolder->getById($fileId) as $node) {
            if ($node instanceof File) {
                return $node;
            }
        }

        return null;
    }

    private function resolveGlobalFolderById(int $folderId): ?Folder
    {
        foreach ($this->rootFolder->getById($folderId) as $node) {
            if ($node instanceof Folder) {
                return $node;
            }
        }

        return null;
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

    private function findWhiteboardInFolder(
        Folder $folder,
        string $projectName,
        ?int $projectId = null,
        ?string $actorUserId = null,
    ): ?File
    {
        $preferred = $projectName !== '' ? $projectName . '.whiteboard' : null;
        $this->logger->debug('Scanning project folder for whiteboard fallback', [
            'projectId' => $projectId,
            'projectName' => $projectName,
            'preferredFileName' => $preferred,
            'actorUserId' => $actorUserId,
        ]);

        foreach ($folder->getDirectoryListing() as $child) {
            if (!$child instanceof File) {
                continue;
            }

            $name = $child->getName();
            if (!is_string($name) || $name === '') {
                $this->logger->debug('Skipping project folder child with invalid file name during whiteboard fallback', [
                    'projectId' => $projectId,
                    'actorUserId' => $actorUserId,
                    'childFileId' => $child->getId(),
                ]);
                continue;
            }

            $lower = strtolower($name);
            if ($preferred !== null && $name === $preferred) {
                $this->logger->debug('Resolved project whiteboard by preferred file name fallback', [
                    'projectId' => $projectId,
                    'actorUserId' => $actorUserId,
                    'resolvedFileId' => $child->getId(),
                    'resolvedFileName' => $name,
                ]);
                return $child;
            }

            if (str_ends_with($lower, '.whiteboard') || str_ends_with($lower, '.excalidraw')) {
                $this->logger->debug('Resolved project whiteboard by extension fallback', [
                    'projectId' => $projectId,
                    'actorUserId' => $actorUserId,
                    'resolvedFileId' => $child->getId(),
                    'resolvedFileName' => $name,
                ]);
                return $child;
            }
        }

        $this->logger->debug('No project whiteboard file was found during folder fallback scan', [
            'projectId' => $projectId,
            'projectName' => $projectName,
            'preferredFileName' => $preferred,
            'actorUserId' => $actorUserId,
        ]);
        return null;
    }

    private function resolveTalkService(string $serviceClass): object
    {
        if (!class_exists($serviceClass)) {
            throw new RuntimeException(sprintf('Talk service "%s" is not available.', $serviceClass));
        }

        return $this->serverContainer->get($serviceClass);
    }
}
