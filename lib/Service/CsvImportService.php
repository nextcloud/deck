<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\Importer\CsvParser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class CsvImportService {

	private const DEFAULT_LABEL_COLORS = [
		'CC317C', '317CCC', '2EA07B', 'F4A331',
		'9C31CC', 'CC3131', '31CC7C', '3131CC',
		'CC7C31', '7C31CC',
	];

	public function __construct(
		private CsvParser $csvParser,
		private StackMapper $stackMapper,
		private CardMapper $cardMapper,
		private LabelMapper $labelMapper,
		private AssignmentMapper $assignmentMapper,
		private AclMapper $aclMapper,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Import cards from CSV content into an existing board.
	 *
	 * @return array{imported: int, updated: int, total: int, stacksCreated: int, labelsCreated: int, errors: string[]}
	 */
	public function importToBoard(int $boardId, string $csvContent, string $userId): array {
		$rows = $this->csvParser->parse($csvContent);
		if (empty($rows)) {
			return ['imported' => 0, 'updated' => 0, 'total' => 0, 'stacksCreated' => 0, 'labelsCreated' => 0, 'errors' => []];
		}

		$existingStacks = $this->stackMapper->findAll($boardId);
		$stackMap = [];
		$maxStackOrder = 0;
		foreach ($existingStacks as $stack) {
			$stackMap[mb_strtolower($stack->getTitle())] = $stack;
			$maxStackOrder = max($maxStackOrder, $stack->getOrder() + 1);
		}

		$existingLabels = $this->labelMapper->findAll($boardId);
		$labelMap = [];
		foreach ($existingLabels as $label) {
			$labelMap[mb_strtolower($label->getTitle())] = $label;
		}

		// Share the board with all assigned users before creating cards
		$this->shareBoardWithAssignedUsers($boardId, $rows, $userId);

		$stacksCreated = 0;
		$labelsCreated = 0;
		$colorIndex = 0;
		$imported = 0;
		$updated = 0;
		$total = count($rows);
		$errors = [];
		$orderPerStack = [];

		foreach ($rows as $row) {
			$stackName = $row['stackName'] ?? '';
			if ($stackName === '') {
				$stackName = 'Imported';
			}
			$stackKey = mb_strtolower($stackName);

			if (!isset($stackMap[$stackKey])) {
				$stack = new Stack();
				$stack->setTitle($stackName);
				$stack->setBoardId($boardId);
				$stack->setOrder($maxStackOrder++);
				$stack->setLastModified(time());
				$this->stackMapper->insert($stack);
				$stackMap[$stackKey] = $stack;
				$stacksCreated++;
			}

			$stackId = $stackMap[$stackKey]->getId();
			if (!isset($orderPerStack[$stackId])) {
				$orderPerStack[$stackId] = 0;
			}

			$cardTitle = $row['title'] ?? '';
			$cardId = $row['id'] ?? null;
			$isUpdate = false;

			try {
				if ($cardId !== null) {
					// Verify the card exists and belongs to this board
					$existingBoardId = $this->cardMapper->findBoardId($cardId);
					if ($existingBoardId === null) {
						$errors[] = 'Card ID ' . $cardId . ' ("' . $cardTitle . '") not found, creating as new card';
						$cardId = null;
					} elseif ($existingBoardId !== $boardId) {
						$errors[] = 'Card ID ' . $cardId . ' ("' . $cardTitle . '") belongs to a different board, creating as new card';
						$cardId = null;
					}
				}

				if ($cardId !== null) {
					// Update existing card
					$card = $this->cardMapper->find($cardId, false);
					$card->setTitle($cardTitle);
					$card->setDescription($row['description'] ?? '');
					$card->setStackId($stackId);
					$card->setDuedate($row['duedate']);

					$createdAt = $row['createdAt'] ? $row['createdAt']->getTimestamp() : null;
					$lastModified = $row['lastModified'] ? $row['lastModified']->getTimestamp() : null;
					if ($createdAt !== null) {
						$card->setCreatedAt($createdAt);
					}
					if ($lastModified !== null) {
						$card->setLastModified($lastModified);
					}

					$this->cardMapper->update($card, false);
					$isUpdate = true;
				} else {
					// Create new card
					$card = new Card();
					$card->setTitle($cardTitle);
					$card->setDescription($row['description'] ?? '');
					$card->setStackId($stackId);
					$card->setType('plain');
					$card->setOrder($orderPerStack[$stackId]++);
					$card->setOwner($userId);
					$card->setDuedate($row['duedate']);

					$createdAt = $row['createdAt'] ? $row['createdAt']->getTimestamp() : null;
					$lastModified = $row['lastModified'] ? $row['lastModified']->getTimestamp() : null;

					$this->cardMapper->insert($card);

					$updateDate = false;
					if ($createdAt !== null && $createdAt !== $card->getCreatedAt()) {
						$card->setCreatedAt($createdAt);
						$updateDate = true;
					}
					if ($lastModified !== null && $lastModified !== $card->getLastModified()) {
						$card->setLastModified($lastModified);
						$updateDate = true;
					}
					if ($updateDate) {
						$this->cardMapper->update($card, false);
					}
				}
			} catch (\Exception $e) {
				$errors[] = 'Failed to import card "' . $cardTitle . '": ' . $e->getMessage();
				$this->logger->warning('Failed to import card "' . $cardTitle . '"', ['exception' => $e]);
				continue;
			}

			// Sync labels
			$this->syncCardLabels($card, $row['tags'], $boardId, $labelMap, $colorIndex, $labelsCreated, $isUpdate, $errors, $cardTitle);

			// Sync user assignments
			$this->syncCardAssignments($card, $row['assignedUsers'], $isUpdate, $errors, $cardTitle);

			if ($isUpdate) {
				$updated++;
			} else {
				$imported++;
			}
		}

		return [
			'imported' => $imported,
			'updated' => $updated,
			'total' => $total,
			'stacksCreated' => $stacksCreated,
			'labelsCreated' => $labelsCreated,
			'errors' => $errors,
		];
	}

	/**
	 * Sync labels on a card: for updates, remove labels not in CSV and add missing ones.
	 */
	private function syncCardLabels(
		Card $card,
		array $tags,
		int $boardId,
		array &$labelMap,
		int &$colorIndex,
		int &$labelsCreated,
		bool $isUpdate,
		array &$errors,
		string $cardTitle,
	): void {
		// Build the desired label set
		$desiredLabelIds = [];
		foreach ($tags as $tag) {
			$tagKey = mb_strtolower($tag);
			if (!isset($labelMap[$tagKey])) {
				$label = new Label();
				$label->setTitle($tag);
				$index = abs($colorIndex) % count(self::DEFAULT_LABEL_COLORS);
				$label->setColor(self::DEFAULT_LABEL_COLORS[$index]);
				$label->setBoardId($boardId);
				$label->setLastModified(time());
				$this->labelMapper->insert($label);
				$labelMap[$tagKey] = $label;
				$colorIndex++;
				$labelsCreated++;
			}
			$desiredLabelIds[$labelMap[$tagKey]->getId()] = $tag;
		}

		// For updates, remove labels that are no longer in the CSV
		if ($isUpdate) {
			$currentLabels = $this->labelMapper->findAssignedLabelsForCard($card->getId());
			foreach ($currentLabels as $currentLabel) {
				if (!isset($desiredLabelIds[$currentLabel->getId()])) {
					try {
						$this->cardMapper->removeLabel($card->getId(), $currentLabel->getId());
					} catch (\Exception $e) {
						$errors[] = 'Failed to remove label "' . $currentLabel->getTitle() . '" from card "' . $cardTitle . '"';
					}
				}
			}
			// Only add labels that aren't already assigned
			$currentLabelIds = array_map(fn ($l) => $l->getId(), $currentLabels);
			foreach ($desiredLabelIds as $labelId => $tag) {
				if (!in_array($labelId, $currentLabelIds, true)) {
					try {
						$this->cardMapper->assignLabel($card->getId(), $labelId);
					} catch (\Exception $e) {
						$errors[] = 'Failed to assign label "' . $tag . '" to card "' . $cardTitle . '"';
					}
				}
			}
		} else {
			foreach ($desiredLabelIds as $labelId => $tag) {
				try {
					$this->cardMapper->assignLabel($card->getId(), $labelId);
				} catch (\Exception $e) {
					$errors[] = 'Failed to assign label "' . $tag . '" to card "' . $cardTitle . '"';
					$this->logger->warning('Failed to assign label "' . $tag . '" to card "' . $cardTitle . '"', ['exception' => $e]);
				}
			}
		}
	}

	/**
	 * Sync user assignments on a card: for updates, remove users not in CSV and add missing ones.
	 */
	private function syncCardAssignments(
		Card $card,
		array $assignedUsers,
		bool $isUpdate,
		array &$errors,
		string $cardTitle,
	): void {
		// Resolve display names to UIDs
		$desiredUids = [];
		foreach ($assignedUsers as $displayName) {
			$users = $this->userManager->searchDisplayName($displayName, 1);
			if (empty($users)) {
				$errors[] = 'User "' . $displayName . '" not found for card "' . $cardTitle . '"';
				continue;
			}
			$user = reset($users);
			$desiredUids[$user->getUID()] = $displayName;
		}

		if ($isUpdate) {
			$currentAssignments = $this->assignmentMapper->findAll($card->getId());
			$currentUids = [];
			foreach ($currentAssignments as $assignment) {
				$currentUids[$assignment->getParticipant()] = $assignment;
			}

			// Remove assignments not in CSV
			foreach ($currentUids as $uid => $assignment) {
				if (!isset($desiredUids[$uid])) {
					try {
						$this->assignmentMapper->delete($assignment);
					} catch (\Exception $e) {
						$errors[] = 'Failed to remove user "' . $uid . '" from card "' . $cardTitle . '"';
					}
				}
			}

			// Add missing assignments
			foreach ($desiredUids as $uid => $displayName) {
				if (!isset($currentUids[$uid])) {
					try {
						$assignment = new Assignment();
						$assignment->setCardId($card->getId());
						$assignment->setParticipant($uid);
						$assignment->setType(Assignment::TYPE_USER);
						$this->assignmentMapper->insert($assignment);
					} catch (\Exception $e) {
						$errors[] = 'Failed to assign user "' . $displayName . '" to card "' . $cardTitle . '"';
					}
				}
			}
		} else {
			foreach ($desiredUids as $uid => $displayName) {
				try {
					$assignment = new Assignment();
					$assignment->setCardId($card->getId());
					$assignment->setParticipant($uid);
					$assignment->setType(Assignment::TYPE_USER);
					$this->assignmentMapper->insert($assignment);
				} catch (\Exception $e) {
					$errors[] = 'Failed to assign user "' . $displayName . '" to card "' . $cardTitle . '"';
					$this->logger->warning('Failed to assign user "' . $displayName . '" to card "' . $cardTitle . '"', ['exception' => $e]);
				}
			}
		}
	}

	/**
	 * Share the board with all users referenced in the CSV before assigning them to cards.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function shareBoardWithAssignedUsers(int $boardId, array $rows, string $ownerUid): void {
		$existingAcls = $this->aclMapper->findAll($boardId);
		$sharedUsers = [];
		foreach ($existingAcls as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				$sharedUsers[$acl->getParticipant()] = true;
			}
		}

		foreach ($rows as $row) {
			foreach ($row['assignedUsers'] as $displayName) {
				$users = $this->userManager->searchDisplayName($displayName, 1);
				if (!empty($users)) {
					$user = reset($users);
					$uid = $user->getUID();

					// Skip the board owner and already-shared users
					if ($uid === $ownerUid || isset($sharedUsers[$uid])) {
						continue;
					}

					try {
						$acl = new Acl();
						$acl->setBoardId($boardId);
						$acl->setType(Acl::PERMISSION_TYPE_USER);
						$acl->setParticipant($uid);
						$acl->setPermissionEdit(true);
						$acl->setPermissionShare(false);
						$acl->setPermissionManage(false);
						$this->aclMapper->insert($acl);
						$sharedUsers[$uid] = true;
					} catch (\Exception $e) {
						$this->logger->warning('Failed to share board with user "' . $uid . '"', ['exception' => $e]);
					}
				}
			}
		}
	}
}
