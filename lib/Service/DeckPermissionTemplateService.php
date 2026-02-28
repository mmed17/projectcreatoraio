<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCA\Deck\NoPermissionException as DeckNoPermissionException;
use OCA\Deck\Service\CardPolicyService;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
use OCA\ProjectCreatorAIO\Db\DeckPermissionTemplate;
use OCA\ProjectCreatorAIO\Db\DeckPermissionTemplateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;

class DeckPermissionTemplateService
{
	public function __construct(
		private readonly IDBConnection $db,
		private readonly DeckPermissionTemplateMapper $templateMapper,
		private readonly CardPolicyService $cardPolicyService,
		private readonly OrganizationUserMapper $organizationUserMapper,
		private readonly IGroupManager $groupManager,
	) {
	}

	/**
	 * Keeps existing behavior: list all templates for the current user's organization.
	 *
	 * @return DeckPermissionTemplate[]
	 */
	public function listForUser(string $userId): array
	{
		$orgId = $this->getOrganizationIdForUser($userId);
		if ($orgId <= 0) {
			return [];
		}
		return $this->templateMapper->findByOrganization($orgId);
	}

	/**
	 * List templates for the organization of the project linked to this board.
	 * Any project member (or project owner/org admin/global admin) may list.
	 *
	 * @return DeckPermissionTemplate[]
	 */
	public function listForBoard(int $boardId, string $userId): array
	{
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$ctx = $this->assertCanAccessProjectForBoard($boardId, $userId);
		return $this->templateMapper->findByOrganization($ctx['organizationId']);
	}

	/**
	 * Only project owners OR organization admins OR global admins may apply templates for a board.
	 */
	public function canApplyForBoard(int $boardId, string $userId): bool
	{
		if ($boardId <= 0) {
			return false;
		}

		$ctx = $this->getProjectContextForBoard($boardId);
		$orgId = (int) ($ctx['organizationId'] ?? 0);
		if ($orgId <= 0) {
			return false;
		}

		if ($this->isGlobalAdmin($userId)) {
			return true;
		}
		if ($this->isOrganizationAdmin($userId, $orgId)) {
			return true;
		}

		$ownerId = (string) ($ctx['ownerId'] ?? '');
		return $ownerId !== '' && $ownerId === $userId;
	}

	/**
	 * Fetch the template payload for applying to this board.
	 * Only project owners OR organization admins OR global admins may fetch payload.
	 *
	 * @return array<string, mixed>
	 */
	public function getForBoard(int $templateId, int $boardId, string $userId): array
	{
		if ($templateId <= 0) {
			throw new OCSBadRequestException('Invalid template');
		}
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$ctx = $this->assertCanApplyForBoard($boardId, $userId);
		$orgId = $ctx['organizationId'];

		try {
			$template = $this->templateMapper->find($templateId);
		} catch (DoesNotExistException $e) {
			throw new OCSNotFoundException('Template not found');
		}

		if ((int) ($template->getOrganizationId() ?? 0) !== $orgId) {
			throw new OCSNotFoundException('Template not found');
		}

		$json = (string) ($template->getTemplateJson() ?? '');
		$payload = json_decode($json, true);
		if (!is_array($payload)) {
			throw new \RuntimeException('Invalid template payload');
		}

		return [
			'id' => (int) ($template->id ?? 0),
			'organizationId' => (int) ($template->getOrganizationId() ?? 0),
			'name' => (string) ($template->getName() ?? ''),
			'createdBy' => (string) ($template->getCreatedBy() ?? ''),
			'createdAt' => $template->getCreatedAt() ? $template->getCreatedAt()->format('c') : null,
			'updatedAt' => $template->getUpdatedAt() ? $template->getUpdatedAt()->format('c') : null,
			'payload' => $payload,
		];
	}

	/**
	 * Any project member may create a template (board context),
	 * but we still enforce Deck's permission check (must be able to manage board).
	 */
	public function createFromBoard(int $boardId, string $name, string $userId): DeckPermissionTemplate
	{
		$name = trim($name);
		if ($name === '') {
			throw new OCSBadRequestException('Template name is required');
		}
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$ctx = $this->assertCanAccessProjectForBoard($boardId, $userId);
		$orgId = $ctx['organizationId'];

		if ($this->templateMapper->findByOrganizationAndName($orgId, $name) !== null) {
			throw new OCSBadRequestException('A template with this name already exists');
		}

		try {
			// Also enforces that the current user can manage this board.
			$policyState = $this->cardPolicyService->getBoardPolicyState($boardId);
			$explicitPolicies = $this->cardPolicyService->getExplicitCardPoliciesByBoard($boardId);
		} catch (DeckNoPermissionException $e) {
			throw new OCSForbiddenException('You do not have permission to manage this board');
		}

		$permissionMode = (string) ($policyState['settings']['permissionMode'] ?? '');
		if ($permissionMode !== 'card_policy') {
			throw new OCSBadRequestException('Granular permissions are not enabled on this board');
		}

		$roles = array_map(static function (array $r): array {
			return [
				'roleKey' => (string) ($r['roleKey'] ?? ''),
				'name' => (string) ($r['name'] ?? ''),
				'color' => (string) ($r['color'] ?? ''),
			];
		}, (array) ($policyState['roles'] ?? []));

		$defaults = (array) ($policyState['defaultRoleKeys'] ?? ['move' => [], 'approve' => [], 'view' => []]);
		$defaults = [
			'move' => array_values(array_unique((array) ($defaults['move'] ?? []))),
			'approve' => array_values(array_unique((array) ($defaults['approve'] ?? []))),
			'view' => array_values(array_unique((array) ($defaults['view'] ?? []))),
		];

		$stacks = $this->loadBoardStacks($boardId);
		$cards = $this->loadBoardCards($boardId);
		$cardsOut = [];
		foreach ($cards as $c) {
			$cardId = (int) ($c['id'] ?? 0);
			$title = (string) ($c['title'] ?? '');
			if ($cardId <= 0 || $title === '') {
				continue;
			}
			$policy = $explicitPolicies[$cardId] ?? ['move' => [], 'approve' => [], 'view' => []];
			$cardsOut[] = [
				'title' => $title,
				'description' => (string) ($c['description'] ?? ''),
				'stackTitle' => (string) ($c['stack_title'] ?? ''),
				'stackOrder' => (int) ($c['stack_order'] ?? 0),
				'order' => (int) ($c['card_order'] ?? 0),
				'policy' => [
					'move' => array_values(array_unique((array) ($policy['move'] ?? []))),
					'approve' => array_values(array_unique((array) ($policy['approve'] ?? []))),
					'view' => array_values(array_unique((array) ($policy['view'] ?? []))),
				],
			];
		}

		$templatePayload = [
			'version' => 1,
			'createdFrom' => [
				'boardId' => $boardId,
				'permissionMode' => (string) (($policyState['settings']['permissionMode'] ?? '') ?: 'legacy'),
			],
			'roles' => array_values(array_filter($roles, static fn (array $r) => ($r['roleKey'] ?? '') !== '')),
			'defaults' => $defaults,
			'stacks' => $stacks,
			'cards' => $cardsOut,
		];

		$json = json_encode($templatePayload, JSON_UNESCAPED_SLASHES);
		if (!is_string($json) || $json === '') {
			throw new \RuntimeException('Unable to serialize template');
		}

		$now = new DateTime('now');
		$template = new DeckPermissionTemplate();
		$template->setOrganizationId($orgId);
		$template->setName($name);
		$template->setCreatedBy($userId);
		$template->setTemplateJson($json);
		$template->setCreatedAt($now);
		$template->setUpdatedAt(null);

		return $this->templateMapper->insert($template);
	}

	/**
	 * Delete a template.
	 * - Global admins OR org admins may delete any template.
	 * - Project owners may delete any template (requires boardId context).
	 * - Template creators may delete their own templates.
	 */
	public function delete(int $templateId, string $userId, ?int $boardId = null): void
	{
		if ($templateId <= 0) {
			throw new OCSBadRequestException('Invalid template');
		}

		try {
			$template = $this->templateMapper->find($templateId);
		} catch (DoesNotExistException $e) {
			throw new OCSNotFoundException('Template not found');
		}

		$orgId = (int) ($template->getOrganizationId() ?? 0);
		$createdBy = (string) ($template->getCreatedBy() ?? '');

		if ($this->isGlobalAdmin($userId)) {
			$this->templateMapper->delete($template);
			return;
		}

		if ($orgId > 0 && $this->isOrganizationAdmin($userId, $orgId)) {
			$this->templateMapper->delete($template);
			return;
		}

		if ($createdBy !== '' && $createdBy === $userId) {
			$this->templateMapper->delete($template);
			return;
		}

		if ($boardId !== null) {
			if ($boardId <= 0) {
				throw new OCSBadRequestException('Invalid board');
			}

			$ctx = $this->getProjectContextForBoard($boardId);
			$boardOrgId = (int) ($ctx['organizationId'] ?? 0);
			if ($boardOrgId <= 0) {
				throw new OCSNotFoundException('This board is not linked to a project');
			}

			if ($boardOrgId !== $orgId) {
				throw new OCSNotFoundException('Template not found');
			}

			$ownerId = (string) ($ctx['ownerId'] ?? '');
			if ($ownerId !== '' && $ownerId === $userId) {
				$this->templateMapper->delete($template);
				return;
			}
		}

		throw new OCSForbiddenException('You do not have permission to delete this template');
	}

	private function isOrganizationAdmin(string $userId, int $organizationId): bool
	{
		if ($organizationId <= 0) {
			return false;
		}
		$membership = $this->organizationUserMapper->getOrganizationMembership($userId);
		if ($membership === null || (int) ($membership['organization_id'] ?? 0) !== $organizationId) {
			return false;
		}
		return (string) ($membership['role'] ?? '') === 'admin';
	}

	private function isGlobalAdmin(string $userId): bool
	{
		return $this->groupManager->isAdmin($userId);
	}

	private function getOrganizationIdForUser(string $userId): int
	{
		if ($this->isGlobalAdmin($userId)) {
			// Global admins must pick an organization elsewhere (MVP: no org-scoped listing for them).
			return 0;
		}
		$membership = $this->organizationUserMapper->getOrganizationMembership($userId);
		return isset($membership['organization_id']) ? (int) $membership['organization_id'] : 0;
	}

	/**
	 * @return array{organizationId: int, ownerId: string, projectGroupGid: string}
	 */
	private function getProjectContextForBoard(int $boardId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('organization_id', 'owner_id', 'project_group_gid')
			->from('custom_projects')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();

		if (!is_array($row)) {
			return [
				'organizationId' => 0,
				'ownerId' => '',
				'projectGroupGid' => '',
			];
		}

		return [
			'organizationId' => isset($row['organization_id']) ? (int) $row['organization_id'] : 0,
			'ownerId' => (string) ($row['owner_id'] ?? ''),
			'projectGroupGid' => (string) ($row['project_group_gid'] ?? ''),
		];
	}

	/**
	 * Any project member (group member), project owner, org admin, or global admin.
	 *
	 * @return array{organizationId: int, ownerId: string, projectGroupGid: string}
	 */
	private function assertCanAccessProjectForBoard(int $boardId, string $userId): array
	{
		$ctx = $this->getProjectContextForBoard($boardId);
		$orgId = (int) ($ctx['organizationId'] ?? 0);
		if ($orgId <= 0) {
			throw new OCSNotFoundException('This board is not linked to a project');
		}

		if ($this->isGlobalAdmin($userId)) {
			return $ctx;
		}
		if ($this->isOrganizationAdmin($userId, $orgId)) {
			return $ctx;
		}

		$ownerId = (string) ($ctx['ownerId'] ?? '');
		if ($ownerId !== '' && $ownerId === $userId) {
			return $ctx;
		}

		$gid = trim((string) ($ctx['projectGroupGid'] ?? ''));
		if ($gid !== '' && $this->groupManager->isInGroup($userId, $gid)) {
			return $ctx;
		}

		throw new OCSForbiddenException('You do not have access to this project');
	}

	/**
	 * Only project owners OR org admins OR global admins.
	 *
	 * @return array{organizationId: int, ownerId: string, projectGroupGid: string}
	 */
	private function assertCanApplyForBoard(int $boardId, string $userId): array
	{
		$ctx = $this->getProjectContextForBoard($boardId);
		$orgId = (int) ($ctx['organizationId'] ?? 0);
		if ($orgId <= 0) {
			throw new OCSNotFoundException('This board is not linked to a project');
		}

		if ($this->isGlobalAdmin($userId)) {
			return $ctx;
		}
		if ($this->isOrganizationAdmin($userId, $orgId)) {
			return $ctx;
		}

		$ownerId = (string) ($ctx['ownerId'] ?? '');
		if ($ownerId !== '' && $ownerId === $userId) {
			return $ctx;
		}

		throw new OCSForbiddenException('You do not have permission to apply templates for this board');
	}

	/**
	 * @return array<int, array{title: string, order: int}>
	 */
	private function loadBoardStacks(int $boardId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('title', 'order')
			->from('deck_stacks')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->orderBy('order', 'ASC');
		$res = $qb->executeQuery();
		$out = [];
		while ($row = $res->fetch()) {
			$title = (string) ($row['title'] ?? '');
			if ($title === '') {
				continue;
			}
			$out[] = [
				'title' => $title,
				'order' => (int) ($row['order'] ?? 0),
			];
		}
		$res->closeCursor();
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadBoardCards(int $boardId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.id', 'c.title', 'c.description', 'c.order', 's.title AS stack_title', 's.order AS stack_order')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('s.order', 'ASC')
			->addOrderBy('c.order', 'ASC');
		$res = $qb->executeQuery();
		$out = [];
		while ($row = $res->fetch()) {
			$out[] = [
				'id' => (int) ($row['id'] ?? 0),
				'title' => (string) ($row['title'] ?? ''),
				'description' => (string) ($row['description'] ?? ''),
				'card_order' => (int) ($row['order'] ?? 0),
				'stack_title' => (string) ($row['stack_title'] ?? ''),
				'stack_order' => (int) ($row['stack_order'] ?? 0),
			];
		}
		$res->closeCursor();
		return $out;
	}
}
