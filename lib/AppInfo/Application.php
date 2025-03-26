<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\AppInfo;

use Closure;
use Exception;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Deck\Capabilities;
use OCA\Deck\Collaboration\Resources\ResourceProvider;
use OCA\Deck\Collaboration\Resources\ResourceProviderCard;
use OCA\Deck\Dashboard\DeckWidgetToday;
use OCA\Deck\Dashboard\DeckWidgetTomorrow;
use OCA\Deck\Dashboard\DeckWidgetUpcoming;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Event\SessionClosedEvent;
use OCA\Deck\Event\SessionCreatedEvent;
use OCA\Deck\Listeners\BeforeTemplateRenderedListener;
use OCA\Deck\Listeners\CommentEventListener;
use OCA\Deck\Listeners\FullTextSearchEventListener;
use OCA\Deck\Listeners\LiveUpdateListener;
use OCA\Deck\Listeners\ParticipantCleanupListener;
use OCA\Deck\Listeners\ResourceAdditionalScriptsListener;
use OCA\Deck\Listeners\ResourceListener;
use OCA\Deck\Middleware\DefaultBoardMiddleware;
use OCA\Deck\Middleware\ExceptionMiddleware;
use OCA\Deck\Notification\Notifier;
use OCA\Deck\Reference\BoardReferenceProvider;
use OCA\Deck\Reference\CardReferenceProvider;
use OCA\Deck\Reference\CommentReferenceProvider;
use OCA\Deck\Reference\CreateCardReferenceProvider;
use OCA\Deck\Search\CardCommentProvider;
use OCA\Deck\Search\DeckProvider;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Sharing\DeckShareProvider;
use OCA\Deck\Sharing\Listener;
use OCA\Deck\Teams\DeckTeamResourceProvider;
use OCA\Text\Event\LoadEditor;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\Comments\CommentsEntityEvent;
use OCP\Comments\CommentsEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Share\IManager;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'deck';

	public const COMMENT_ENTITY_TYPE = 'deckCard';

	private $referenceLoaded = false;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		// TODO move this back to ::register after fixing the autoload issue
		// (and use a listener class)
		$container = $this->getContainer();
		$eventDispatcher = $container->get(IEventDispatcher::class);
		$eventDispatcher->addListener(RenderReferenceEvent::class, function (RenderReferenceEvent $e) use ($eventDispatcher) {
			Util::addScript(self::APP_ID, self::APP_ID . '-reference');
			if (!$this->referenceLoaded && class_exists(LoadEditor::class)) {
				$this->referenceLoaded = true;
				$eventDispatcher->dispatchTyped(new LoadEditor());
			}
		});
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsEntity']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCollaborationResources']));

		$context->injectFn(function (IManager $shareManager) {
			$shareManager->registerShareProvider(DeckShareProvider::class);
		});

		$context->injectFn(function (Listener $listener, IEventDispatcher $eventDispatcher) {
			$listener->register($eventDispatcher);
		});
	}

	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

		$context->registerCapability(Capabilities::class);
		$context->registerMiddleWare(ExceptionMiddleware::class);
		$context->registerMiddleWare(DefaultBoardMiddleware::class);

		$context->registerService('databaseType', static function (ContainerInterface $c) {
			return $c->get(IConfig::class)->getSystemValue('dbtype', 'sqlite');
		});
		$context->registerService('database4ByteSupport', static function (ContainerInterface $c) {
			return $c->get(IDBConnection::class)->supports4ByteText();
		});

		$context->registerSearchProvider(DeckProvider::class);
		$context->registerSearchProvider(CardCommentProvider::class);
		$context->registerDashboardWidget(DeckWidgetUpcoming::class);
		$context->registerDashboardWidget(DeckWidgetToday::class);
		$context->registerDashboardWidget(DeckWidgetTomorrow::class);

		$context->registerReferenceProvider(CreateCardReferenceProvider::class);

		// reference widget
		$context->registerReferenceProvider(CardReferenceProvider::class);
		$context->registerReferenceProvider(BoardReferenceProvider::class);
		$context->registerReferenceProvider(CommentReferenceProvider::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		// Event listening for full text search indexing
		$context->registerEventListener(CardCreatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(CardUpdatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(CardDeletedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclCreatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclUpdatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclDeletedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(CommentsEvent::class, CommentEventListener::class);

		// Handling cache invalidation for collections
		$context->registerEventListener(AclCreatedEvent::class, ResourceListener::class);
		$context->registerEventListener(AclDeletedEvent::class, ResourceListener::class);

		$context->registerEventListener(UserDeletedEvent::class, ParticipantCleanupListener::class);
		$context->registerEventListener(GroupDeletedEvent::class, ParticipantCleanupListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, ParticipantCleanupListener::class);

		// Event listening for realtime updates via notify_push
		$context->registerEventListener(SessionCreatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(SessionClosedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(BoardUpdatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(CardCreatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(CardUpdatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(CardDeletedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(AclCreatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(AclUpdatedEvent::class, LiveUpdateListener::class);
		$context->registerEventListener(AclDeletedEvent::class, LiveUpdateListener::class);

		$context->registerNotifierService(Notifier::class);
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, ResourceAdditionalScriptsListener::class);

		$context->registerTeamResourceProvider(DeckTeamResourceProvider::class);
	}

	public function registerCommentsEntity(IEventDispatcher $eventDispatcher): void {
		$eventDispatcher->addListener(CommentsEntityEvent::EVENT_ENTITY, function (CommentsEntityEvent $event) {
			$event->addEntityCollection(self::COMMENT_ENTITY_TYPE, function ($name) {
				/** @var CardMapper */
				$cardMapper = $this->getContainer()->get(CardMapper::class);
				/** @var PermissionService $permissionService */
				$permissionService = $this->getContainer()->get(PermissionService::class);

				try {
					return $permissionService->checkPermission($cardMapper, (int)$name, Acl::PERMISSION_READ);
				} catch (\Exception $e) {
					return false;
				}
			});
		});
	}

	protected function registerCollaborationResources(IProviderManager $resourceManager): void {
		$resourceManager->registerResourceProvider(ResourceProvider::class);
		$resourceManager->registerResourceProvider(ResourceProviderCard::class);
	}
}
