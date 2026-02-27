<?php

namespace OCA\ProjectCreatorAIO\AppInfo;

use OCA\ProjectCreatorAIO\Dashboard\ProjectsWidget;
use OCA\ProjectCreatorAIO\Service\DeckDefaultCardsService;
use OCA\ProjectCreatorAIO\Service\TimelinePlanningService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\CardPolicyService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\StackService;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'projectcreatoraio';
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
	
	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(ProjectsWidget::class);

        $context->registerService('ProjectMapper', function (IAppContainer $c) {
            return new ProjectMapper(
                $c->getServer()->getDatabaseConnection()
            );
        });

		$context->registerService(DeckDefaultCardsService::class, function (IAppContainer $c) {
			return new DeckDefaultCardsService(
				$c->getServer()->query(CardService::class),
				$c->getServer()->query(CardPolicyService::class),
				$c->getServer()->query(LabelService::class),
				$c->getServer()->query(StackService::class),
				$c->getServer()->query(BoardService::class),
				$c->getServer()->get(LoggerInterface::class),
			);
		});

		$context->registerService(TimelinePlanningService::class, function (IAppContainer $c) {
			return new TimelinePlanningService(
				$c->getServer()->getDatabaseConnection(),
				$c->getServer()->get(LoggerInterface::class),
			);
		});

	}

	public function boot(IBootContext $context): void {}
}
