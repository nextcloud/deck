<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;

/** @template-extends DeckMapper<Attachment> */
class AttachmentMapper extends DeckMapper implements IPermissionMapper {
	private $cardMapper;
	private $userManager;
	private $qb;

	/**
	 * AttachmentMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param CardMapper $cardMapper
	 * @param IUserManager $userManager
	 */
	public function __construct(IDBConnection $db, CardMapper $cardMapper, IUserManager $userManager) {
		parent::__construct($db, 'deck_attachment', Attachment::class);
		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
		$this->qb = $this->db->getQueryBuilder();
	}

	/**
	 * @param int $id
	 * @return Attachment
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find($id) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $cardId
	 * @param string $data
	 * @return Attachment
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByData($cardId, $data) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('data', $qb->createNamedParameter($data, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $cardId
	 * @return Entity[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll($cardId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));


		return $this->findEntities($qb);
	}

	/**
	 * @param null $cardId
	 * @param bool $withOffset
	 * @return array
	 */
	public function findToDelete($cardId = null, $withOffset = true) {
		// add buffer of 5 min
		$timeLimit = time() - (60 * 5);
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->gt('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		if ($withOffset) {
			$qb
				->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($timeLimit, IQueryBuilder::PARAM_INT)));
		}
		if ($cardId !== null) {
			$qb
				->andWhere($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));
		}

		return $this->findEntities($qb);
	}


	/**
	 * Check if $userId is owner of Entity with $id
	 *
	 * @param $userId string userId
	 * @param $id int|string unique entity identifier
	 * @return boolean
	 */
	public function isOwner($userId, $id): bool {
		try {
			$attachment = $this->find($id);
			return $this->cardMapper->isOwner($userId, $attachment->getCardId());
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
		}
		return false;
	}

	/**
	 * Query boardId for Entity of given $id
	 *
	 * @param $id int|string unique entity identifier
	 * @return int|null id of Board
	 */
	public function findBoardId($id): ?int {
		try {
			$attachment = $this->find($id);
		} catch (\Exception $e) {
			return null;
		}
		return $this->cardMapper->findBoardId($attachment->getCardId());
	}
}
