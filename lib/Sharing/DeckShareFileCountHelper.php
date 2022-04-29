<?php

/*
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Sharing;

use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\BadRequestException;
use OCP\Share\IShare;
use OCP\IDBConnection;

class DeckShareFileCountHelper {

	/**
	 * @var array
	 */
	private $processors = [];
	private AttachmentMapper $attachmentMapper;

	public function __construct(AttachmentMapper $attachmentMapper) {
		$this->attachmentMapper = $attachmentMapper;
		$this->registerProcessors();
	}

	/**
	 * @param $cardId
	 * @return int
	 * @throws BadRequestException
	 */
	public function getAttachmentCount($cardId) {
		$count = $this->countDeckFile($cardId);
		foreach (array_keys($this->processors) as $type) {
			if ($processor = $this->processor($type)) {
				$count += $this->{$processor}((int)$cardId);
			}
		}

		return $count;
	}

	/**
	 * @param string $type
	 * @return string|null
	 * @throws BadRequestException
	 */
	private function processor(string $type) {
		if (!array_key_exists($type, $this->processors)) {
			throw new BadRequestException('This type of file is not found');
		}

		return $this->processors[$type];
	}

	private function registerProcessors() {
		return $this->processors = [
			'deck_file' => null,
			'file' => 'countFile'
		];
	}

	/**
	 * Count deck files
	 *
	 * @param $cardId
	 * @return int
	 */
	private function countDeckFile($cardId) {
		return count($this->attachmentMapper->findAll($cardId));
	}

	/**
	 * Count files other than the deck ones
	 *
	 * @param int $cardId
	 * @return int
	 */
	private function countFile(int $cardId) {
		/** @var IDBConnection $qb */
		$db = \OC::$server->getDatabaseConnection();
		$qb = $db->getQueryBuilder();
		$qb->select('s.id', 'f.fileid', 'f.path')
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->andWhere($qb->expr()->eq('s.share_with', $qb->createNamedParameter($cardId)))
			->andWhere($qb->expr()->isNull('s.parent'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			));

		$count = 0;
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			if ($this->isAccessibleResult($data)) {
				$count++;
			}
		}
		$cursor->closeCursor();
		return $count;
	}

	private function isAccessibleResult(array $data): bool {
		// exclude shares leading to deleted file entries
		if ($data['fileid'] === null || $data['path'] === null) {
			return false;
		}

		// exclude shares leading to trashbin on home storages
		$pathSections = explode('/', $data['path'], 2);
		// FIXME: would not detect rare md5'd home storage case properly
		if (
			$pathSections[0] !== 'files'
			&& in_array(explode(':', $data['storage_string_id'], 2)[0], ['home', 'object'])
		) {
			return false;
		}
		return true;
	}
}
