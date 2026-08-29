<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\BoardView;
use OCA\Deck\Db\BoardViewMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class BoardViewService {
	public function __construct(
		private BoardViewMapper $boardViewMapper,
		private BoardService $boardService,
		private ?string $userId,
	) {
	}

	/**
	 * @return BoardView[]
	 */
	public function findAll(int $boardId): array {
		$this->boardService->find($boardId);
		return $this->boardViewMapper->findAll($boardId, $this->userId ?? '');
	}

	/**
	 * @return BoardView[]
	 */
	public function findAllForUser(): array {
		return $this->boardViewMapper->findAllForUser($this->userId ?? '');
	}

	public function create(int $boardId, string $name, array $filters): BoardView {
		$this->boardService->find($boardId);
		if ($name === '') {
			throw new BadRequestException('The name must not be empty');
		}

		$view = new BoardView();
		$view->setBoardId($boardId);
		$view->setName($name);
		$view->setFilters(json_encode($filters));
		$view->setOwner($this->userId ?? '');
		$view->setCreatedAt(time());
		$view->setLastModifiedAt(time());
		return $this->boardViewMapper->insert($view);
	}

	public function update(int $boardId, int $viewId, string $name, array $filters): BoardView {
		$this->boardService->find($boardId);
		$view = $this->boardViewMapper->find($viewId, $this->userId ?? '');
		if ($view->getBoardId() !== $boardId) {
			throw new BadRequestException('The view does not belong to the given board');
		}

		if ($name !== '') {
			$view->setName($name);
		}
		$view->setFilters(json_encode($filters));
		$view->setLastModifiedAt(time());
		return $this->boardViewMapper->update($view);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function delete(int $boardId, int $viewId): void {
		$this->boardService->find($boardId);
		$view = $this->boardViewMapper->find($viewId, $this->userId ?? '');
		if ($view->getBoardId() !== $boardId) {
			throw new BadRequestException('The view does not belong to the given board');
		}
		$this->boardViewMapper->delete($view);
	}
}
