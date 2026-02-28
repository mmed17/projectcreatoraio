<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Controller;

use OCA\ProjectCreatorAIO\Service\DeckPermissionTemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

class DeckTemplateApiController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly DeckPermissionTemplateService $templateService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function list(?int $boardId = null): DataResponse
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('Authentication required');
		}

		if ($boardId !== null) {
			$items = $this->templateService->listForBoard($boardId, $user->getUID());
		} else {
			$items = $this->templateService->listForUser($user->getUID());
		}
		return new DataResponse(array_map(static fn ($t) => $t->jsonSerialize(), $items));
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function createFromBoard(int $boardId, string $name): DataResponse
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('Authentication required');
		}
		$template = $this->templateService->createFromBoard($boardId, $name, $user->getUID());
		return new DataResponse($template->jsonSerialize());
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function delete(int $templateId): DataResponse
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('Authentication required');
		}
		$this->templateService->delete($templateId, $user->getUID());
		return new DataResponse(['status' => 'deleted']);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function get(int $templateId, int $boardId): DataResponse
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('Authentication required');
		}
		$data = $this->templateService->getForBoard($templateId, $boardId, $user->getUID());
		return new DataResponse($data);
	}
}
