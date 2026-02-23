<?php

namespace OCA\ProjectCreatorAIO\Service;

class ProjectTypeDeckDefaults
{
	public const TYPE_COMBI = 0;

	/** @return string[] */
	public static function getConditionalSet1Titles(): array
	{
		return [
			'Hoogbouwoverleg inplannen',
			'VO inpandige tekeningen',
			'DO inpandige tekeningen',
			'Verslag inpandig overleg',
			'Blokkenschema',
		];
	}

	/** @return string[] */
	public static function getConditionalSet2Titles(): array
	{
		return [
			'Aanvraag particuliere grond',
			'Bodemrapport',
			'Saneringsevaluatierapport',
			'Zakelijkrecht',
		];
	}

	/**
	 * Map canonical card title to accepted aliases on existing boards.
	 *
	 * @return array<string, string[]>
	 */
	public static function getCardTitleAliases(): array
	{
		return [
			'Blokkenschema' => ['Blokkenschema', 'Blokkenshema'],
		];
	}

	/** @return array<int, array{title: string, important: bool}> */
	public static function getNextPriorityCards(int $projectType): array
	{
		if ($projectType !== self::TYPE_COMBI) {
			return [];
		}

		return [
			['title' => 'Piekvermogensformulier', 'important' => true],
			['title' => 'Situatie tekening', 'important' => true],
			['title' => 'Intakeformulier', 'important' => true],
			['title' => 'Quickscan', 'important' => true],
			['title' => 'AVP', 'important' => true],
		];
	}

	/** @return array<int, array{title: string, important: bool}> */
	public static function getProcessStepCards(int $projectType): array
	{
		if ($projectType !== self::TYPE_COMBI) {
			return [];
		}

		return [
			['title' => 'Garantie overeenkomst', 'important' => false],
			['title' => 'VO', 'important' => true],
			['title' => 'DO', 'important' => true],
			['title' => 'Intake inplannen & hosten', 'important' => false],
			['title' => 'Intakeverslag', 'important' => false],
			['title' => 'Huisnummerbesluit', 'important' => true],
			['title' => 'Hoogbouwoverleg inplannen', 'important' => false],
			['title' => 'VO inpandige tekeningen', 'important' => false],
			['title' => 'DO inpandige tekeningen', 'important' => false],
			['title' => 'Verslag inpandig overleg', 'important' => true],
			['title' => 'Blokkenschema', 'important' => false],
			['title' => 'Aanvraag particuliere grond', 'important' => false],
			['title' => 'Bodemrapport', 'important' => true],
			['title' => 'Saneringsevaluatierapport', 'important' => false],
			['title' => 'Zakelijkrecht', 'important' => false],
		];
	}

	/** @return string[] */
	public static function getRequiredNextPriorityTitles(int $projectType): array
	{
		$cards = self::getNextPriorityCards($projectType);
		return array_values(array_map(static fn (array $item) => (string) ($item['title'] ?? ''), $cards));
	}

	public static function getDefaultPreparationWeeks(int $projectType): int
	{
		return 0;
	}
}
