<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Service;

use OCA\ProjectCreatorAIO\Db\Project;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class ProjectMemberResolver {
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	/**
	 * @param string[] $excludedUids
	 * @return IUser[]
	 */
	public function getProjectMembers(Project $project, array $excludedUids = []): array {
		$members = [];
		$seen = [];
		foreach ($excludedUids as $excludedUid) {
			$uid = trim((string) $excludedUid);
			if ($uid !== '') {
				$seen[$uid] = true;
			}
		}

		$groupGid = trim((string) ($project->getProjectGroupGid() ?? ''));
		if ($groupGid !== '') {
			$group = $this->groupManager->get($groupGid);
			foreach ($group?->getUsers() ?? [] as $member) {
				$uid = trim((string) $member->getUID());
				if ($uid === '' || isset($seen[$uid])) {
					continue;
				}

				$seen[$uid] = true;
				$members[] = $member;
			}
		}

		$ownerUid = trim((string) ($project->getOwnerId() ?? ''));
		if ($ownerUid !== '' && !isset($seen[$ownerUid])) {
			$owner = $this->userManager->get($ownerUid);
			if ($owner !== null) {
				$members[] = $owner;
			}
		}

		return $members;
	}
}
