<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCA\Files\Event\LoadSidebar;
use OCA\Text\Event\LoadEditor;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent as CollaborationResourcesEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class PageController extends Controller {
	public function __construct(
		string $AppName,
		IRequest $request,
		private PermissionService $permissionService,
		private IInitialState $initialState,
		private BoardService $boardService,
		private ConfigService $configService,
		private IEventDispatcher $eventDispatcher,
		private CardMapper $cardMapper,
		private IURLGenerator $urlGenerator,
		private CardService $cardService,
		private IConfig $config,
	) {
		parent::__construct($AppName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('maxUploadSize', (int)\OCP\Util::uploadLimit());
		$this->initialState->provideInitialState('canCreate', $this->permissionService->canCreate());
		$this->initialState->provideInitialState('config', $this->configService->getAll());

		$this->initialState->provideInitialState('initialBoards', $this->boardService->findAll());

		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		$this->eventDispatcher->dispatchTyped(new CollaborationResourcesEvent());
		if (class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}
		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}

		$response = new TemplateResponse('deck', 'main', [
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);

		if ($this->config->getSystemValueBool('debug', false)) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedScriptDomain('*');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexList(): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexBoard(int $boardId): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexBoardDetails(int $boardId): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexCard(int $cardId): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function redirectToCard($cardId): RedirectResponse {
		try {
			$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
			return new RedirectResponse($this->cardService->getCardUrl($cardId));
		} catch (\Exception $e) {
			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('deck.page.index'));
		}
	}
}
