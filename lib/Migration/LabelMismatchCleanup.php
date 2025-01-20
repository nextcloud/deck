<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class LabelMismatchCleanup implements IRepairStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getName() {
		return 'Migrate labels with wrong board mapping';
	}

	public function run(IOutput $output) {
		// Find assingments where a label of another (wrong) board is used
		$qb = $this->db->getQueryBuilder();
		$qb->select('al.id', 'al.label_id', 'al.card_id', 's.board_id as actual_board_id', 'l.board_id as wrong_id', 'l.color', 'l.title')
			->from('deck_assigned_labels', 'al')
			->innerJoin('al', 'deck_cards', 'c', 'c.id = al.card_id')
			->innerJoin('c', 'deck_stacks', 's', 'c.stack_id = s.id')
			->innerJoin('al', 'deck_labels', 'l', 'l.id = al.label_id')
			->where($qb->expr()->neq('l.board_id', 's.board_id'));

		$labels = $qb->executeQUery()->fetchAll();
		if (count($labels) === 0) {
			return;
		}

		$output->info('Found ' . count($labels) . ' labels with wrong board mapping');

		foreach ($labels as $label) {
			// Select existing label on the correct board
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('deck_labels')
				->where($qb->expr()->eq('title', $qb->createNamedParameter($label['title'])))
				->andWhere($qb->expr()->eq('color', $qb->createNamedParameter($label['color'])))
				->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($label['actual_board_id'])));
			$result = $qb->executeQuery();
			$newLabel = $result->fetchOne();
			$result->closeCursor();

			if (!$newLabel) {
				// Create a new label with the same title and color on the correct board
				$qb = $this->db->getQueryBuilder();
				$qb->insert('deck_labels')
					->values([
						'title' => $qb->createNamedParameter($label['title']),
						'color' => $qb->createNamedParameter($label['color']),
						'board_id' => $qb->createNamedParameter($label['actual_board_id']),
					]);
				$qb->executeStatement();
				$newLabel = $qb->getLastInsertId();
				$output->debug('Created new label ' . $label['title'] . ' on board ' . $label['actual_board_id']);
			} else {
				$output->debug('Found existing label ' . $label['title'] . ' on board ' . $label['actual_board_id']);
			}

			// Update the assignment to use the new label
			$qb = $this->db->getQueryBuilder();
			$qb->update('deck_assigned_labels')
				->set('label_id', $qb->createNamedParameter($newLabel))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($label['id'])));
			$qb->executeStatement();
			$output->debug('Updated label assignment ' . $label['id'] . ' to use label ' . $newLabel);
		}
	}
}
