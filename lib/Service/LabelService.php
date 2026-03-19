<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Errors\InternalError;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\LabelServiceValidator;
use Psr\Log\LoggerInterface;

class LabelService {

	public function __construct(
		private LabelMapper $labelMapper,
		private PermissionService $permissionService,
		private BoardService $boardService,
		private ChangeHelper $changeHelper,
		private LabelServiceValidator $labelServiceValidator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $labelId): Label {
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);
		return $this->labelMapper->find($labelId);
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function create(string $title, string $color, int $boardId): Label {
		$this->labelServiceValidator->check(compact('title', 'color', 'boardId'));

		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

		$boardLabels = $this->labelMapper->findAll($boardId);
		foreach ($boardLabels as $boardLabel) {
			if ($boardLabel->getTitle() === $title) {
				throw new BadRequestException('title must be unique');
				break;
			}
		}

		if ($this->boardService->isArchived(null, $boardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$label = new Label();
		$label->setTitle($title);
		$label->setColor($color);
		$label->setBoardId($boardId);
		$this->changeHelper->boardChanged($boardId);

		return $this->labelMapper->insert($label);
	}

	public function cloneLabelIfNotExists(int $labelId, int $targetBoardId): Label {
		$this->permissionService->checkPermission(null, $targetBoardId, Acl::PERMISSION_MANAGE);
		$boardLabels = $this->boardService->find($targetBoardId)->getLabels();
		$originLabel = $this->find($labelId);
		$filteredValues = array_values(array_filter($boardLabels, fn ($item) => $item->getTitle() === $originLabel->getTitle()));
		if (empty($filteredValues)) {
			return $this->create($originLabel->getTitle(), $originLabel->getColor(), $targetBoardId);
		}

		return $filteredValues[0];
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete(int $id): Label {
		$this->labelServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->labelMapper, $id, Acl::PERMISSION_MANAGE);
		if ($this->boardService->isArchived($this->labelMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$label = $this->labelMapper->delete($this->find($id));
		$this->changeHelper->boardChanged($label->getBoardId());

		return $label;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update(int $id, string $title, string $color): Label {
		$this->labelServiceValidator->check(compact('title', 'color', 'id'));

		$this->permissionService->checkPermission($this->labelMapper, $id, Acl::PERMISSION_MANAGE);

		$label = $this->find($id);

		$boardLabels = $this->labelMapper->findAll($label->getBoardId());
		foreach ($boardLabels as $boardLabel) {
			if ($boardLabel->getId() === $label->getId()) {
				continue;
			}
			if ($boardLabel->getTitle() === $title) {
				throw new BadRequestException('title must be unique');
				break;
			}
		}

		if ($this->boardService->isArchived($this->labelMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$label->setTitle($title);
		$label->setColor($color);
		$this->changeHelper->boardChanged($label->getBoardId());

		return $this->labelMapper->update($label);
	}

	/**
	 * @param Board $board
	 * @param array $label
	 *
	 * @return void
	 *
	 * @throws InternalError
	 */
	public function importBoardLabel(Board $board, array $label): void {
		$labelEntity = new Label();
		$labelEntity->setBoardId($board->getId());
		$labelEntity->setTitle($label['title']);
		$labelEntity->setColor($label['color']);
		$labelEntity->setLastModified($label['lastModified']);
		try {
			$this->labelMapper->insert($labelEntity);
		} catch (\Throwable $e) {
			$this->logger->error('importBoardLabel insert error: ' . $e->getMessage());
			throw new InternalError('importBoardLabel insert error: ' . $e->getMessage());
		}
	}
}
