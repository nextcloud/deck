<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\StackAutomation;
use OCA\Deck\Service\StackAutomationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class StackAutomationController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private StackAutomationService $automationService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all automations for a stack
	 *
	 * @return StackAutomation[]
	 */
	#[NoAdminRequired]
	public function index(int $stackId): array {
		return $this->automationService->getAutomations($stackId);
	}

	/**
	 * Create a new automation
	 */
	#[NoAdminRequired]
	public function create(int $stackId, string $event, string $actionType, array $config, int $order = 0): JSONResponse {
		try {
			$automation = $this->automationService->createAutomation($stackId, $event, $actionType, $config, $order);
			return new JSONResponse($automation, 201);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 400);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'Failed to create automation'], 500);
		}
	}

	/**
	 * Update an automation
	 */
	#[NoAdminRequired]
	public function update(int $id, string $event, string $actionType, array $config, int $order): JSONResponse {
		try {
			$automation = $this->automationService->updateAutomation($id, $event, $actionType, $config, $order);
			return new JSONResponse($automation);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 400);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'Failed to update automation'], 500);
		}
	}

	/**
	 * Delete an automation
	 */
	#[NoAdminRequired]
	public function delete(int $id): JSONResponse {
		try {
			$this->automationService->deleteAutomation($id);
			return new JSONResponse(['success' => true]);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'Failed to delete automation'], 500);
		}
	}
}
