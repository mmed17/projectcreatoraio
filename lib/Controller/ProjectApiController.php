<?php
namespace OCA\Projectcreatoraio\Controller;

use OCA\ProjectCreatorAIO\Service\ProjectService;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectNote;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Db\ProjectNoteMapper;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Http\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\File;
use Throwable;

class ProjectApiController extends Controller
{
    public const APP_ID = 'projectcreatoraio';

    public function __construct(
        string $appName,
        IRequest $request,
        protected IUserSession $userSession,
        protected ProjectMapper $projectMapper,
        protected ProjectNoteMapper $noteMapper,
        protected ProjectService $projectService,
        private IGroupManager $iGroupManager,
        private OrganizationUserMapper $organizationUserMapper,
        private IRootFolder $rootFolder,
    ) {
        parent::__construct($appName, $request);
        $this->request = $request;
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function get(int $projectId)
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $notes = $this->projectService->getProjectNotes($projectId);
        $payload = $project->jsonSerialize();
        $payload['public_note'] = $notes['public'] ?? '';
        $payload['private_note'] = $notes['private'] ?? '';
        $payload['private_note_available'] = (bool) ($notes['private_available'] ?? true);

        return new DataResponse($payload);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function listMembers(int $projectId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $members = $this->projectService->getProjectMembers($projectId);

        return new DataResponse([
            'members' => $members,
        ]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function addMember(int $projectId, string $userId = ''): DataResponse
    {
        $params = $this->request->getParams();
        if (is_array($params) && array_key_exists('userId', $params) && is_string($params['userId'])) {
            $userId = $params['userId'];
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $result = $this->projectService->addMemberToProject($projectId, $userId);

        return new DataResponse($result, $result['added'] ? 201 : 200);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function updateNotes(
        int $projectId,
        ?string $public_note = null,
        ?string $private_note = null,
    ): DataResponse {
        // Some setups don't map snake_case JSON keys reliably to method args.
        // Fall back to raw request params while still allowing empty-string updates.
        $params = $this->request->getParams();
        if (is_array($params)) {
            if (array_key_exists('public_note', $params)) {
                $public_note = is_string($params['public_note']) ? $params['public_note'] : '';
            }
            if (array_key_exists('private_note', $params)) {
                $private_note = is_string($params['private_note']) ? $params['private_note'] : '';
            }
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $notes = $this->projectService->updateProjectNotes($projectId, $public_note, $private_note);

        return new DataResponse([
            'public_note' => $notes['public'] ?? '',
            'private_note' => $notes['private'] ?? '',
            'private_note_available' => (bool) ($notes['private_available'] ?? true),
        ]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function listNotes(int $projectId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $notes = $this->projectService->getProjectNotesList($projectId, $currentUser->getUID());

        return new DataResponse([
            'notes' => $notes,
            'canCreatePublic' => true,
            'canCreatePrivate' => $notes['private_available'] ?? true,
        ]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function getNote(int $projectId, int $noteId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $note = $this->noteMapper->find($noteId);
        if ($note === null) {
            throw new OCSNotFoundException("Note with ID $noteId not found");
        }

        if ($note->getProjectId() !== $projectId) {
            throw new OCSNotFoundException("Note not found for this project");
        }

        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        // Private notes can only be accessed by their creator
        if ($note->getVisibility() === 'private' && $note->getUserId() !== $currentUser->getUID()) {
            throw new OCSForbiddenException('You do not have permission to view this note');
        }

        return new DataResponse($note->jsonSerialize());
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function createNote(
        int $projectId,
        string $title,
        string $content,
        string $visibility = 'public'
    ): DataResponse {
        $params = $this->request->getParams();
        if (is_array($params)) {
            if (array_key_exists('title', $params)) {
                $title = is_string($params['title']) ? $params['title'] : '';
            }
            if (array_key_exists('content', $params)) {
                $content = is_string($params['content']) ? $params['content'] : '';
            }
            if (array_key_exists('visibility', $params)) {
                $visibility = is_string($params['visibility']) ? $params['visibility'] : 'public';
            }
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        // Validate visibility
        if ($visibility !== 'public' && $visibility !== 'private') {
            return new DataResponse(['message' => 'Invalid visibility. Use "public" or "private"'], 400);
        }

        // Check if private notes are available
        if ($visibility === 'private') {
            $hasPrivateFolder = $this->projectService->hasPrivateFolderForUser($projectId, $currentUser->getUID());
            if (!$hasPrivateFolder) {
                return new DataResponse(['message' => 'Private notes are not available for this user'], 403);
            }
        }

        $note = $this->noteMapper->createNote(
            $projectId,
            $currentUser->getUID(),
            $title,
            $content,
            $visibility
        );

        return new DataResponse($note->jsonSerialize(), 201);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function updateNote(
        int $projectId,
        int $noteId,
        ?string $title = null,
        ?string $content = null
    ): DataResponse {
        $params = $this->request->getParams();
        if (is_array($params)) {
            if (array_key_exists('title', $params)) {
                $title = is_string($params['title']) ? $params['title'] : '';
            }
            if (array_key_exists('content', $params)) {
                $content = is_string($params['content']) ? $params['content'] : '';
            }
        }

        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $note = $this->noteMapper->find($noteId);
        if ($note === null) {
            throw new OCSNotFoundException("Note with ID $noteId not found");
        }

        if ($note->getProjectId() !== $projectId) {
            throw new OCSNotFoundException("Note not found for this project");
        }

        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        // Only the creator can update the note
        if ($note->getUserId() !== $currentUser->getUID()) {
            throw new OCSForbiddenException('You do not have permission to update this note');
        }

        if ($title !== null) {
            $note->setTitle($title);
        }
        if ($content !== null) {
            $note->setContent($content);
        }

        $updatedNote = $this->noteMapper->updateNote($note);

        return new DataResponse($updatedNote->jsonSerialize());
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function deleteNote(int $projectId, int $noteId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);

        $note = $this->noteMapper->find($noteId);
        if ($note === null) {
            throw new OCSNotFoundException("Note with ID $noteId not found");
        }

        if ($note->getProjectId() !== $projectId) {
            throw new OCSNotFoundException("Note not found for this project");
        }

        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        // Only the creator can delete the note
        if ($note->getUserId() !== $currentUser->getUID()) {
            throw new OCSForbiddenException('You do not have permission to delete this note');
        }

        $success = $this->noteMapper->deleteNote($noteId);

        return new DataResponse(['deleted' => $success]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function create(
        string $name,
        string $number,
        int $type,
        array $members = [],
        string $groupId = '',
        ?int $organizationId = null,
        string $description = '',
        ?string $client_name = null,
        ?string $client_role = null,
        ?string $client_phone = null,
        ?string $client_email = null,
        ?string $client_address = null,
        ?string $loc_street = null,
        ?string $loc_city = null,
        ?string $loc_zip = null,
        ?string $request_date = null,
        ?string $desired_execution_date = null,
        ?int $required_preparation_days = null,
    ): DataResponse {

        $params = $this->request->getParams();
        if (is_array($params)) {
            if (array_key_exists('request_date', $params)) {
                $request_date = is_string($params['request_date']) ? $params['request_date'] : null;
            }
            if (array_key_exists('desired_execution_date', $params)) {
                $desired_execution_date = is_string($params['desired_execution_date']) ? $params['desired_execution_date'] : null;
            }
            if (array_key_exists('required_preparation_days', $params)) {
                $raw = $params['required_preparation_days'];
                if (is_int($raw)) {
                    $required_preparation_days = $raw;
                } elseif (is_string($raw) && $raw !== '' && is_numeric($raw)) {
                    $required_preparation_days = (int) $raw;
                }
            }
        }

        if ($organizationId === null && $groupId !== '' && ctype_digit($groupId)) {
            $organizationId = (int) $groupId;
        }

        try {
            $project = $this->projectService->createProject(
                $name,
                $number,
                $type,
                $members,
                $description,
                $organizationId,
                $client_name,
                $client_role,
                $client_phone,
                $client_email,
                $client_address,
                $loc_street,
                $loc_city,
                $loc_zip,
                $request_date,
                $desired_execution_date,
                $required_preparation_days,
            );

            return new DataResponse([
                'message' => 'Project created successfully',
                'projectId' => $project->getId(),
            ]);

        } catch (Throwable $e) {
            $statusCode = (int) $e->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return new DataResponse([
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], $statusCode);
        }
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function searchUsers(
        string $search = '',
        ?int $organizationId = null,
        int $limit = 25,
        int $offset = 0,
    ): DataResponse {
        $users = $this->projectService->searchUsers($search, $organizationId, $limit, $offset);
        return new DataResponse(['users' => $users]);
    }


    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function list(): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $isAdmin = $this->iGroupManager->isAdmin($currentUser->getUID());
        if ($isAdmin) {
            $results = $this->projectMapper->list();
        } else {
            $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
            if ($membership === null) {
                throw new OCSForbiddenException('You are not assigned to an organization');
            }

            if ($membership['role'] === 'admin') {
                $results = $this->projectMapper->findByOrganizationId((int) $membership['organization_id']);
            } else {
                $results = $this->projectMapper->findByUserIdAndOrganizationId(
                    $currentUser->getUID(),
                    (int) $membership['organization_id'],
                );
            }
        }

        return new DataResponse($results);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function context(): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $userId = $currentUser->getUID();
        $isGlobalAdmin = $this->iGroupManager->isAdmin($userId);
        $membership = $this->organizationUserMapper->getOrganizationMembership($userId);

        return new DataResponse([
            'userId' => $userId,
            'isGlobalAdmin' => $isGlobalAdmin,
            'organizationRole' => $membership['role'] ?? null,
            'organizationId' => isset($membership['organization_id']) ? (int) $membership['organization_id'] : null,
        ]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function getProjectFiles(int $projectId): DataResponse
    {
        $project = $this->projectMapper->find($projectId);
        if ($project === null) {
            throw new OCSNotFoundException("Project with ID $projectId not found");
        }

        $this->assertCanAccessProject($project);
        $files = $this->projectService->getProjectFiles($projectId);
        return new DataResponse(['files' => $files]);
    }

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getWhiteboardInfo(int $projectId): DataResponse {
		$project = $this->projectMapper->find($projectId);
		if ($project === null) {
			throw new OCSNotFoundException("Project with ID $projectId not found");
		}

		$this->assertCanAccessProject($project);
		$currentUser = $this->userSession->getUser();
		if ($currentUser === null) {
			throw new OCSForbiddenException('Authentication required');
		}

		$whiteboardIdRaw = $project->getWhiteBoardId();
		$whiteboardId = ($whiteboardIdRaw !== null && $whiteboardIdRaw !== '') ? (int)$whiteboardIdRaw : 0;
		if ($whiteboardId <= 0) {
			throw new OCSNotFoundException('Whiteboard not linked');
		}

		$userFolder = $this->rootFolder->getUserFolder($currentUser->getUID());
		$file = null;
		$node = $userFolder->getFirstNodeById($whiteboardId);
		if ($node instanceof File) {
			$file = $node;
		}

		if ($file === null) {
			$folderNode = $userFolder->get($project->getFolderPath());
			if ($folderNode instanceof Folder) {
				$file = $this->findWhiteboardInFolder($folderNode, $project->getName());
			}
		}

		if ($file === null) {
			throw new OCSNotFoundException('Whiteboard file not found');
		}

		$relative = $userFolder->getRelativePath($file->getPath());
		if (!is_string($relative) || $relative === '') {
			throw new OCSNotFoundException('Whiteboard path not accessible');
		}

		return new DataResponse([
			'fileId' => $file->getId(),
			'name' => $file->getName(),
			'mimetype' => $file->getMimeType(),
			'size' => $file->getSize(),
			'mtime' => $file->getMTime(),
			'path' => '/' . ltrim($relative, '/'),
		]);
	}

	private function findWhiteboardInFolder(Folder $folder, string $projectName): ?File {
		$preferred = trim($projectName) !== '' ? $projectName . '.whiteboard' : null;
		foreach ($folder->getDirectoryListing() as $child) {
			if ($child instanceof File) {
				$name = $child->getName();
				$lower = strtolower($name);
				if ($preferred !== null && $name === $preferred) {
					return $child;
				}
				if (str_ends_with($lower, '.whiteboard') || str_ends_with($lower, '.excalidraw')) {
					return $child;
				}
			}
		}

		return null;
	}

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function getByBoardId(int $boardId): DataResponse
    {
        $project = $this->projectMapper->findByBoardId($boardId);
        if ($project === null) {
            throw new OCSNotFoundException("Project not found for board $boardId");
        }

        $this->assertCanAccessProject($project);
        return new DataResponse($project);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function listByUser(string $userId): DataResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        $isGlobalAdmin = $this->iGroupManager->isAdmin($currentUser->getUID());
        if (!$isGlobalAdmin) {
            $currentMembership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
            if ($currentMembership === null) {
                throw new OCSForbiddenException('You are not assigned to an organization');
            }

            if ($currentMembership['role'] !== 'admin') {
                if ($currentUser->getUID() !== $userId) {
                    throw new OCSForbiddenException('Members can only view their own projects');
                }

                $projects = $this->projectMapper->findByUserIdAndOrganizationId(
                    $userId,
                    (int) $currentMembership['organization_id'],
                );

                return new DataResponse($projects);
            }

            $targetMembership = $this->organizationUserMapper->getOrganizationMembership($userId);
            if ($targetMembership === null || (int) $targetMembership['organization_id'] !== (int) $currentMembership['organization_id']) {
                throw new OCSNotFoundException('User not found in your organization');
            }

            $projects = $this->projectMapper->findByUserIdAndOrganizationId(
                $userId,
                (int) $currentMembership['organization_id'],
            );

            return new DataResponse($projects);
        }

        $projects = $this->projectMapper->findByUserId($userId);
        return new DataResponse($projects);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function update(
        int $id,
        ?string $name = null,
        ?string $number = null,
        ?int $type = null,
        ?string $description = null,
        ?string $client_name = null,
        ?string $client_role = null,
        ?string $client_phone = null,
        ?string $client_email = null,
        ?string $client_address = null,
        ?string $loc_street = null,
        ?string $loc_city = null,
        ?string $loc_zip = null,
        ?string $external_ref = null,
        ?int $status = null
    ): DataResponse {
        $existingProject = $this->projectMapper->find($id);
        if ($existingProject === null) {
            throw new OCSNotFoundException("Project with ID $id not found");
        }

        $this->assertCanAccessProject($existingProject);

        $isAdminForProject = $this->canAdministerProject($existingProject);
        if (!$isAdminForProject) {
            $restrictedFields = [
                'name',
                'number',
                'type',
                'description',
                'external_ref',
                'status',
            ];

            $providedFields = array_keys($this->request->getParams());
            $attemptedRestrictedFields = array_values(array_intersect($restrictedFields, $providedFields));
            if ($attemptedRestrictedFields !== []) {
                throw new OCSForbiddenException('Project members can only update client and location details');
            }
        }

        $updatedProject = $this->projectService->updateProjectDetails(
            $id,
            $name,
            $number,
            $type,
            $description,
            $client_name,
            $client_role,
            $client_phone,
            $client_email,
            $client_address,
            $loc_street,
            $loc_city,
            $loc_zip,
            $external_ref,
            $status,
        );
        return new DataResponse($updatedProject);
    }

    private function canAdministerProject(Project $project): bool
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            return false;
        }

        if ($this->iGroupManager->isAdmin($currentUser->getUID())) {
            return true;
        }

        $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
        if ($membership === null) {
            return false;
        }

        if ((int) $membership['organization_id'] !== (int) $project->getOrganizationId()) {
            return false;
        }

        return $membership['role'] === 'admin';
    }

    private function assertCanAccessProject(Project $project): void
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            throw new OCSForbiddenException('Authentication required');
        }

        if ($this->iGroupManager->isAdmin($currentUser->getUID())) {
            return;
        }

        $membership = $this->organizationUserMapper->getOrganizationMembership($currentUser->getUID());
        if ($membership === null) {
            throw new OCSForbiddenException('You are not assigned to an organization');
        }

        if ((int) $membership['organization_id'] !== (int) $project->getOrganizationId()) {
            throw new OCSNotFoundException('Project not found');
        }

        if ($membership['role'] === 'admin') {
            return;
        }

        if (!$this->isProjectGroupMember($currentUser->getUID(), $project->getProjectGroupGid())) {
            throw new OCSNotFoundException('Project not found');
        }
    }

    private function isProjectGroupMember(string $userId, string $projectGroupGid): bool
    {
        if ($projectGroupGid === '') {
            return false;
        }

        return $this->iGroupManager->isInGroup($userId, $projectGroupGid);
    }
}
