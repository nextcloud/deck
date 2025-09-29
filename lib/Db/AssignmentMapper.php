<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Db;

use OCA\Deck\NotFoundException;
use OCA\Deck\Service\CirclesService;
use OCP\AppFramework\Db\Entity;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

/** @template-extends DeckMapper<Assignment> */
class AssignmentMapper extends DeckMapper implements IPermissionMapper {

	/** @var CardMapper */
	private $cardMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var CirclesService */
	private $circleService;

	public function __construct(IDBConnection $db, CardMapper $cardMapper, IUserManager $userManager, IGroupManager $groupManager, CirclesService $circleService) {
		parent::__construct($db, 'deck_assigned_users', Assignment::class);

		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->circleService = $circleService;
	}

	public function findAll(int $cardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_assigned_users')
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));
		$users = $this->findEntities($qb);
		foreach ($users as $user) {
			$this->mapParticipant($user);
		}
		return $users;
	}

	public function findIn(array $cardIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_assigned_users')
			->where($qb->expr()->in('card_id', $qb->createParameter('cardIds')));

		$users = iterator_to_array($this->chunkQuery($cardIds, function (array $ids) use ($qb) {
			$qb->setParameter('cardIds', $ids, IQueryBuilder::PARAM_INT_ARRAY);
			return $this->findEntities($qb);
		}));

		foreach ($users as $user) {
			$this->mapParticipant($user);
		}
		return $users;
	}

	public function findByParticipant(string $participant, $type = Assignment::TYPE_USER): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_assigned_users')
			->where($qb->expr()->eq('participant', $qb->createNamedParameter($participant, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function deleteByParticipantOnBoard(string $participant, int $boardId, $type = Assignment::TYPE_USER) {
		// Step 1: Get all card IDs for the board that have assignments for this participant
		// This avoids MySQL Error 1093 by separating the SELECT from the DELETE operation
		$cardIdQuery = $this->db->getQueryBuilder();
		$cardIdQuery->select('a.card_id')
			->from('deck_assigned_users', 'a')
			->innerJoin('a', 'deck_cards', 'c', 'c.id = a.card_id')
			->innerJoin('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->where($cardIdQuery->expr()->eq('a.participant', $cardIdQuery->createNamedParameter($participant, IQueryBuilder::PARAM_STR)))
			->andWhere($cardIdQuery->expr()->eq('s.board_id', $cardIdQuery->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($cardIdQuery->expr()->eq('a.type', $cardIdQuery->createNamedParameter($type, IQueryBuilder::PARAM_INT)));

		$result = $cardIdQuery->executeQuery();
		$cardIds = [];
		while ($row = $result->fetch()) {
			$cardIds[] = $row['card_id'];
		}
		$result->closeCursor();

		// Step 2: If we have card IDs, delete the assignments
		if (!empty($cardIds)) {
			$deleteQuery = $this->db->getQueryBuilder();
			$deleteQuery->delete('deck_assigned_users')
				->where($deleteQuery->expr()->eq('participant', $deleteQuery->createNamedParameter($participant, IQueryBuilder::PARAM_STR)))
				->andWhere($deleteQuery->expr()->eq('type', $deleteQuery->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
				->andWhere($deleteQuery->expr()->in('card_id', $deleteQuery->createNamedParameter($cardIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleteQuery->executeStatement();
		}
	}


	public function isOwner(string $userId, int $id): bool {
		return $this->cardMapper->isOwner($userId, $id);
	}

	public function findBoardId(int $id): ?int {
		return $this->cardMapper->findBoardId($id);
	}

	/**
	 * Check if user exists before assigning it to a card
	 *
	 * @param Entity $entity
	 * @return Assignment
	 * @throws NotFoundException
	 */
	public function insert(Entity $entity): Entity {
		if (!($entity instanceof Assignment)) {
			throw new \LogicException('Trying to insert a ' . get_class($entity) . ' in the assignment mapper');
		}
		$origin = $this->getOrigin($entity);
		if ($origin === null) {
			throw new NotFoundException('No origin found for assignment');
		}

		/** @var Assignment $assignment */
		$assignment = parent::insert($entity);
		$this->mapParticipant($assignment);
		return $assignment;
	}

	public function mapParticipant(Assignment $assignment): void {
		$self = $this;
		$assignment->resolveRelation('participant', function () use (&$self, &$assignment) {
			return $self->getOrigin($assignment);
		});
	}

	public function isUserAssigned(int $cardId, string $userId): bool {
		$assignments = $this->findAll($cardId);
		foreach ($assignments as $assignment) {
			$origin = $this->getOrigin($assignment);
			if ($origin instanceof User && $assignment->getParticipant() === $userId) {
				return true;
			}
			if ($origin instanceof Group && $this->groupManager->isInGroup($userId, $assignment->getParticipant())) {
				return true;
			}
			if ($origin instanceof Circle && $this->circleService->isUserInCircle($assignment->getParticipant(), $userId)) {
				return true;
			}
		}

		return false;
	}

	private function getOrigin(Assignment $assignment) {
		if ($assignment->getType() === Assignment::TYPE_USER) {
			$origin = $this->userManager->userExists($assignment->getParticipant());
			return $origin ? new User($assignment->getParticipant(), $this->userManager) : null;
		}
		if ($assignment->getType() === Assignment::TYPE_GROUP) {
			$origin = $this->groupManager->get($assignment->getParticipant());
			return $origin ? new Group($origin) : null;
		}
		if ($assignment->getType() === Assignment::TYPE_CIRCLE) {
			$origin = $this->circleService->getCircle($assignment->getParticipant());
			return $origin ? new Circle($origin) : null;
		}
		return null;
	}

	public function remapAssignedUser(int $boardId, string $userId, string $newUserId): void {
		$subQuery = $this->db->getQueryBuilder();
		$subQuery->selectAlias('a.id', 'id')
			->from('deck_assigned_users', 'a')
			->innerJoin('a', 'deck_cards', 'c', 'c.id = a.card_id')
			->innerJoin('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->where($subQuery->expr()->eq('a.type', $subQuery->createNamedParameter(Assignment::TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($subQuery->expr()->eq('a.participant', $subQuery->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($subQuery->expr()->eq('s.board_id', $subQuery->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1000);

		$qb = $this->db->getQueryBuilder();
		$qb->update('deck_assigned_users')
			->set('participant', $qb->createParameter('participant'))
			->where($qb->expr()->in('id', $qb->createParameter('ids')));

		$moreResults = true;
		do {
			$result = $subQuery->executeQuery();
			$ids = array_map(function ($item) {
				return $item['id'];
			}, $result->fetchAll());

			if (count($ids) === 0 || $result->rowCount() === 0) {
				$moreResults = false;
			}

			$qb->setParameter('participant', $newUserId, IQueryBuilder::PARAM_STR);
			$qb->setParameter('ids', $ids, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeStatement();
		} while ($moreResults === true);

		$result->closeCursor();
	}
}
