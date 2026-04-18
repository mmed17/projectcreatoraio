<?php

namespace OCA\ProjectCreatorAIO\AppInfo;

use OCA\ProjectCreatorAIO\BackgroundJob\DetectStaleProjectsJob;
use OCA\ProjectCreatorAIO\BackgroundJob\ProcessPendingFileProcessingJob;
use OCA\ProjectCreatorAIO\BackgroundJob\PurgeArchivedProjectsJob;
use OCA\ProjectCreatorAIO\BackgroundJob\SendProjectDigestJob;
use OCA\ProjectCreatorAIO\BackgroundJob\ShareProjectWhiteboardInTalkJob;
use OCA\ProjectCreatorAIO\Db\PrivateFolderLinkMapper;
use OCA\ProjectCreatorAIO\Dashboard\ProjectsWidget;
use OCA\ProjectCreatorAIO\Listener\FileProcessingWrittenListener;
use OCA\ProjectCreatorAIO\Listener\WhiteboardWrittenListener;
use OCA\ProjectCreatorAIO\Notification\Notifier;
use OCA\ProjectCreatorAIO\Service\DeckDefaultCardsService;
use OCA\ProjectCreatorAIO\Service\ProjectDeckActivityService;
use OCA\ProjectCreatorAIO\Service\ProjectDigestService;
use OCA\ProjectCreatorAIO\Service\ProjectRetentionService;
use OCA\ProjectCreatorAIO\Service\TimelinePlanningService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\CardPolicyService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\StackService;
use OCP\Files\Events\Node\NodeWrittenEvent;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'projectcreatoraio';
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
	
	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(ProjectsWidget::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerEventListener(NodeWrittenEvent::class, WhiteboardWrittenListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, FileProcessingWrittenListener::class);

        $context->registerService('ProjectMapper', function (IAppContainer $c) {
            return new ProjectMapper(
                $c->getServer()->getDatabaseConnection(),
                $c->query(PrivateFolderLinkMapper::class),
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

		$context->registerService(DetectStaleProjectsJob::class, function (IAppContainer $c) {
			return new DetectStaleProjectsJob(
				$c->getServer()->get(ITimeFactory::class),
				$c->getServer()->query(ProjectDeckActivityService::class),
			);
		});

		$context->registerService(SendProjectDigestJob::class, function (IAppContainer $c) {
			return new SendProjectDigestJob(
				$c->getServer()->get(ITimeFactory::class),
				$c->getServer()->query(ProjectDigestService::class),
			);
		});

		$context->registerService(PurgeArchivedProjectsJob::class, function (IAppContainer $c) {
			return new PurgeArchivedProjectsJob(
				$c->getServer()->get(ITimeFactory::class),
				$c->getServer()->query(ProjectRetentionService::class),
			);
		});

		$context->registerService(ProcessPendingFileProcessingJob::class, function (IAppContainer $c) {
			return new ProcessPendingFileProcessingJob(
				$c->getServer()->get(ITimeFactory::class),
				$c->getServer()->query(\OCA\ProjectCreatorAIO\Service\FileProcessingPipelineService::class),
			);
		});

		$context->registerService(ShareProjectWhiteboardInTalkJob::class, function (IAppContainer $c) {
			return new ShareProjectWhiteboardInTalkJob(
				$c->getServer()->get(ITimeFactory::class),
				$c->query('ProjectMapper'),
				$c->getServer()->get(\OCP\IUserManager::class),
				$c->query(\OCA\ProjectCreatorAIO\Service\ProjectTalkIntegrationService::class),
				$c->getServer()->get(LoggerInterface::class),
			);
		});

	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IJobList $jobList): void {
			if (!$jobList->has(DetectStaleProjectsJob::class, null)) {
				$jobList->add(DetectStaleProjectsJob::class);
			}
			if (!$jobList->has(SendProjectDigestJob::class, null)) {
				$jobList->add(SendProjectDigestJob::class);
			}
			if (!$jobList->has(PurgeArchivedProjectsJob::class, null)) {
				$jobList->add(PurgeArchivedProjectsJob::class);
			}
			if (!$jobList->has(ProcessPendingFileProcessingJob::class, null)) {
				$jobList->add(ProcessPendingFileProcessingJob::class);
			}
		});
	}
}
