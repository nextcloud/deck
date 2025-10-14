<?php

namespace OCA\Deck\Service;
use OCA\Deck\Db\ExternalBoardMapper;
use OCP\IUserManager;

class ExternalBoardService {
	public function __construct(
		private ExternalBoardMapper $externalBoardMapper,
		private  IUserManager $userManager,
		private string $userId,
	) {
	}

	public function findAll() {
		return $this->externalBoardMapper->findAllForUser($this->userId);
	}
}
