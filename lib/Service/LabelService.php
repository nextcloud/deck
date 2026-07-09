<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\LabelServiceValidator;

class LabelService {

	public function __construct(
		private LabelMapper $labelMapper,
		private PermissionService $permissionService,
		private BoardService $boardService,
		private ChangeHelper $changeHelper,
		private LabelServiceValidator $labelServiceValidator,
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

		if ($this->labelMapper->existsByBoardIdAndTitle($boardId, $title)) {
			throw new BadRequestException('title must be unique');
		}

		if ($this->boardService->isArchived(null, $boardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$label = new Label();
		$label->setTitle($title);
		$label->setColor($color);
		$label->setBoardId($boardId);
		$existingLabels = $this->labelMapper->findAll($boardId);
		$maxOrder = null;
		foreach ($existingLabels as $existingLabel) {
			if ($existingLabel->getOrder() !== null) {
				$maxOrder = $maxOrder === null ? $existingLabel->getOrder() : max($maxOrder, $existingLabel->getOrder());
			}
		}
		if ($maxOrder !== null) {
			$label->setOrder($maxOrder + 1);
		}
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

		if ($this->labelMapper->existsByBoardIdAndTitle($label->getBoardId(), $title, $label->getId())) {
			throw new BadRequestException('title must be unique');
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
	 * Set the manual sort order of all labels of a board.
	 *
	 * @param int[] $labelIds every label id of the board, in the wanted order
	 * @return Label[] the board labels in their new order
	 * @throws BadRequestException
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 */
	public function reorder(int $boardId, array $labelIds): array {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

		if ($this->boardService->isArchived(null, $boardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$byId = [];
		foreach ($this->labelMapper->findAll($boardId) as $label) {
			$byId[$label->getId()] = $label;
		}

		$labelIds = array_values(array_unique(array_map('intval', $labelIds)));
		foreach ($labelIds as $labelId) {
			if (!isset($byId[$labelId])) {
				throw new BadRequestException('Label ' . $labelId . ' does not belong to board ' . $boardId);
			}
		}
		if (count($labelIds) !== count($byId)) {
			throw new BadRequestException('The ordered list must contain every label of the board exactly once');
		}

		foreach ($labelIds as $position => $labelId) {
			$label = $byId[$labelId];
			if ($label->getOrder() !== $position) {
				$label->setOrder($position);
				$this->labelMapper->update($label);
			}
		}
		$this->changeHelper->boardChanged($boardId);

		return $this->labelMapper->findAll($boardId);
	}
}
