<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ExternalBoard> */
class ExternalBoardMapper extends QBMapper{
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'deck_boards_external', ExternalBoard::class);
	}

	public function findAllForUser(string $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_boards_external')
			->where($qb->expr()->eq('participant', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('id');
		return $this->findEntities($qb);
	}
}
