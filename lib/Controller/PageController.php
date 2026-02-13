<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Controller;

use OCA\ProjectCreatorAIO\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse|NotFoundResponse {
		$currentUser = $this->userSession->getUser();
		if ($currentUser === null) {
			return new NotFoundResponse();
		}

		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}
}
