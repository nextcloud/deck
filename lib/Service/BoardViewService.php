<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use JsonException;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\BoardView;
use OCA\Deck\Db\BoardViewMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class BoardViewService {
	private const MAX_VIEWS_PER_BOARD = 50;
	private const MAX_NAME_LENGTH = 255;
	private const MAX_FILTERS_SIZE = 60000;

	public function __construct(
		private BoardViewMapper $boardViewMapper,
		private BoardService $boardService,
		private ConfigService $configService,
		private ?string $userId,
	) {
	}

	/**
	 * @return BoardView[]
	 */
	public function findAll(int $boardId): array {
		$this->boardService->find($boardId, false);
		return $this->boardViewMapper->findAll($boardId, $this->userId ?? '');
	}

	/**
	 * @return BoardView[]
	 */
	public function findAllForUser(): array {
		return $this->boardViewMapper->findAllForUser($this->userId ?? '');
	}

	public function create(int $boardId, string $name, array $filters): BoardView {
		$this->boardService->find($boardId, false);
		$name = $this->validateName($name, true);
		if (count($this->boardViewMapper->findAll($boardId, $this->userId ?? '')) >= self::MAX_VIEWS_PER_BOARD) {
			throw new BadRequestException('You have reached the maximum number of saved views for this board');
		}

		$view = new BoardView();
		$view->setBoardId($boardId);
		$view->setName($name);
		$view->setFilters($this->encodeFilters($filters));
		$view->setOwner($this->userId ?? '');
		$view->setCreatedAt(time());
		$view->setLastModifiedAt(time());
		return $this->boardViewMapper->insert($view);
	}

	public function update(int $boardId, int $viewId, string $name, array $filters): BoardView {
		$this->boardService->find($boardId, false);
		$view = $this->boardViewMapper->find($viewId, $this->userId ?? '');
		if ($view->getBoardId() !== $boardId) {
			throw new BadRequestException('The view does not belong to the given board');
		}

		$name = $this->validateName($name, false);
		if ($name !== '') {
			$view->setName($name);
		}
		$view->setFilters($this->encodeFilters($filters));
		$view->setLastModifiedAt(time());
		return $this->boardViewMapper->update($view);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function delete(int $boardId, int $viewId): void {
		$this->boardService->find($boardId, false);
		$view = $this->boardViewMapper->find($viewId, $this->userId ?? '');
		if ($view->getBoardId() !== $boardId) {
			throw new BadRequestException('The view does not belong to the given board');
		}
		$this->boardViewMapper->delete($view);

		if ($this->configService->getDefaultBoardView() === $viewId) {
			$this->configService->set('defaultBoardView', null);
		}
	}

	private function validateName(string $name, bool $required): string {
		$name = trim($name);
		if ($name === '') {
			if ($required) {
				throw new BadRequestException('The name must not be empty');
			}
			return '';
		}
		if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new BadRequestException('The name must not be longer than ' . self::MAX_NAME_LENGTH . ' characters');
		}
		return $name;
	}

	private function encodeFilters(array $filters): string {
		try {
			$encoded = json_encode($filters, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new BadRequestException('The filters are not valid JSON');
		}
		if (strlen($encoded) > self::MAX_FILTERS_SIZE) {
			throw new BadRequestException('The filters are too large');
		}
		return $encoded;
	}
}
