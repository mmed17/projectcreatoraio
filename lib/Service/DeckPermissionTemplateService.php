<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCA\Deck\Service\CardPolicyService;
use OCA\Deck\NoPermissionException as DeckNoPermissionException;
use OCA\Organization\Db\UserMapper as OrganizationUserMapper;
use OCA\ProjectCreatorAIO\Db\DeckPermissionTemplate;
use OCA\ProjectCreatorAIO\Db\DeckPermissionTemplateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IGroupManager;
use OCP\IDBConnection;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;

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
	 * @return DeckPermissionTemplate[]
	 */
	public function listForBoard(int $boardId, string $userId): array
	{
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$orgId = $this->getOrganizationIdForBoard($boardId);
		if ($orgId <= 0) {
			throw new OCSNotFoundException('This board is not linked to a project');
		}

		$this->assertOrganizationAdmin($userId, $orgId);
		return $this->templateMapper->findByOrganization($orgId);
	}

	public function getForBoard(int $templateId, int $boardId, string $userId): array
	{
		if ($templateId <= 0) {
			throw new OCSBadRequestException('Invalid template');
		}
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$orgId = $this->getOrganizationIdForBoard($boardId);
		if ($orgId <= 0) {
			throw new OCSNotFoundException('This board is not linked to a project');
		}
		$this->assertOrganizationAdmin($userId, $orgId);

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

	public function createFromBoard(int $boardId, string $name, string $userId): DeckPermissionTemplate
	{
		$name = trim($name);
		if ($name === '') {
			throw new OCSBadRequestException('Template name is required');
		}
		if ($boardId <= 0) {
			throw new OCSBadRequestException('Invalid board');
		}

		$orgId = $this->getOrganizationIdForBoard($boardId);
		if ($orgId <= 0) {
			throw new OCSNotFoundException('This board is not linked to a project');
		}

		$this->assertOrganizationAdmin($userId, $orgId);
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

	public function delete(int $templateId, string $userId): void
	{
		try {
			$template = $this->templateMapper->find($templateId);
		} catch (DoesNotExistException $e) {
			throw new OCSNotFoundException('Template not found');
		}
		$orgId = (int) ($template->getOrganizationId() ?? 0);
		$this->assertOrganizationAdmin($userId, $orgId);
		$this->templateMapper->delete($template);
	}

	private function assertOrganizationAdmin(string $userId, int $organizationId): void
	{
		if ($organizationId <= 0) {
			throw new OCSForbiddenException('Missing organization context');
		}
		if ($this->groupManager->isAdmin($userId)) {
			return;
		}
		$membership = $this->organizationUserMapper->getOrganizationMembership($userId);
		if ($membership === null || (int) ($membership['organization_id'] ?? 0) !== $organizationId) {
			throw new OCSForbiddenException('You are not a member of this organization');
		}
		if ((string) ($membership['role'] ?? '') !== 'admin') {
			throw new OCSForbiddenException('Only organization admins can manage templates');
		}
	}

	private function getOrganizationIdForUser(string $userId): int
	{
		if ($this->groupManager->isAdmin($userId)) {
			// Global admins must pick an organization elsewhere (MVP: no org-scoped listing for them).
			return 0;
		}
		$membership = $this->organizationUserMapper->getOrganizationMembership($userId);
		return isset($membership['organization_id']) ? (int) $membership['organization_id'] : 0;
	}

	private function getOrganizationIdForBoard(int $boardId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('organization_id')
			->from('custom_projects')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return isset($row['organization_id']) ? (int) $row['organization_id'] : 0;
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
