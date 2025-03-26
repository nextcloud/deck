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

	/** @var LabelMapper */
	private $labelMapper;
	/** @var PermissionService */
	private $permissionService;
	/** @var BoardService */
	private $boardService;
	/** @var ChangeHelper */
	private $changeHelper;
	/** @var LabelServiceValidator */
	private LabelServiceValidator $labelServiceValidator;

	public function __construct(
		LabelMapper $labelMapper,
		PermissionService $permissionService,
		BoardService $boardService,
		ChangeHelper $changeHelper,
		LabelServiceValidator $labelServiceValidator,
	) {
		$this->labelMapper = $labelMapper;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->changeHelper = $changeHelper;
		$this->labelServiceValidator = $labelServiceValidator;
	}

	/**
	 * @param $labelId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find($labelId) {
		if (is_numeric($labelId) === false) {
			throw new BadRequestException('label id must be a number');
		}
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);
		return $this->labelMapper->find($labelId);
	}

	/**
	 * @param $title
	 * @param $color
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function create($title, $color, $boardId) {
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
			$label = $this->create($originLabel->getTitle(), $originLabel->getColor(), $targetBoardId);
			return $label;
		}
		return $originLabel;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id) {
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
	 * @param $id
	 * @param $title
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $color) {
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
}
