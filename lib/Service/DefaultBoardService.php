<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;

class DefaultBoardService {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly BoardMapper $boardMapper,
		private readonly BoardService $boardService,
		private readonly StackService $stackService,
		private readonly CardService $cardService,
		private readonly IConfig $config,
		private readonly LabelService $labelService,
		private readonly AttachmentService $attachmentService,
	) {
	}

	/**
	 * Return true if this is the first time a user is acessing their instance with deck enabled
	 *
	 * @param $userId
	 * @return bool
	 */
	public function checkFirstRun($userId): bool {
		$firstRun = $this->config->getUserValue($userId, Application::APP_ID, 'firstRun', 'yes');

		if ($firstRun === 'yes') {
			try {
				$this->config->setUserValue($userId, Application::APP_ID, 'firstRun', 'no');
			} catch (PreConditionNotMetException $e) {
				return false;
			}
			return true;
		}

		return false;
	}

	private function getDefaultBoardData(): array {
		$defaultBoardDataJson = file_get_contents(__DIR__ . '/fixtures/default-board.json');
		return json_decode($defaultBoardDataJson, true);
	}

	/**
	 * @param $userId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCA\Deck\StatusException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function createDefaultBoard(string $title, string $userId, string $color) {
		$boardData = $this->getDefaultBoardData();

		/** @var Board $defaultBoard */
		$defaultBoard = $this->boardService->create(
			$boardData['title'] ?? $title,
			$userId,
			$boardData['color'] ?? $color,
		);
		$boardId = $defaultBoard->getId();
		$additionLabels = [];
		$translatedLabelTitles = [
			'Read more inside' => $this->l10n->t('Read more inside'),
		];
		$translatedStackTitles = [
			'Custom lists - click to rename!' => $this->l10n->t('Custom lists - click to rename!'),
			'To Do' => $this->l10n->t('To Do'),
			'In Progress' => $this->l10n->t('In Progress'),
			'Done' => $this->l10n->t('Done'),
		];
		$translatedCardTitles = [
			'1. Open to learn more about boards and cards' => $this->l10n->t('1. Open to learn more about boards and cards'),
			'2. Drag cards left and right, up and down' => $this->l10n->t('2. Drag cards left and right, up and down'),
			'3. Apply rich formatting and link content' => $this->l10n->t('3. Apply rich formatting and link content'),
			'4. Share, comment and collaborate!' => $this->l10n->t('4. Share, comment and collaborate!'),
			'Create your first card!' => $this->l10n->t('Create your first card!'),
		];
		$translatedCardDescriptions = [
			"## Welcome to Nextcloud Deck!\n\nNextcloud Deck is a kanban style project management app that integrates seamlessly with the Nextcloud ecosystem. Here, you can create and manage boards to streamline your projects and organize project tasks visually using cards. A versatile tool, Nextcloud Deck will help you stay efficient in both personal and collaborative settings!\n\n### 📌 How to set up a new board?\n\nAdd a new board via the “+” button or “Add board”. Name your board and add a description to clarify its purpose if you wish.\n\nAdd task lists to your board via “Add List” (e.g., \"To Do\"). Add cards to these lists to represent individual tasks or items. Organize your cards visually by assigning labels or colors.\n\nManage your board list: In the board menu next to the board name, you can edit, clone, archive and export your boards.\n\n### 🗂️ How to create and edit task cards?\n\nAdd cards via the “+” button on top of the list of your choice. Name your card and add a description using the available rich text formatting instruments.\n\nSet a due date to track deadlines and add responsible team members. Label your cards with tags for better navigation. You can create and manage tags in the sidebar, which is accessible via the toolbar button.\n\n### 💡Useful tips for Nextcloud Deck\n\n- Integrate Nextcloud Deck with Nextcloud Calendar and track the upcoming card deadlines.\n- Use comments to keep discussions around a task within its dedicated card.\n- Enable Nextcloud Deck notifications to always stay updated on changes to your boards." => $this->l10n->t("## Welcome to Nextcloud Deck!\n\nNextcloud Deck is a kanban style project management app that integrates seamlessly with the Nextcloud ecosystem. Here, you can create and manage boards to streamline your projects and organize project tasks visually using cards. A versatile tool, Nextcloud Deck will help you stay efficient in both personal and collaborative settings!\n\n### 📌 How to set up a new board?\n\nAdd a new board via the “+” button or “Add board”. Name your board and add a description to clarify its purpose if you wish.\n\nAdd task lists to your board via “Add List” (e.g., \"To Do\"). Add cards to these lists to represent individual tasks or items. Organize your cards visually by assigning labels or colors.\n\nManage your board list: In the board menu next to the board name, you can edit, clone, archive and export your boards.\n\n### 🗂️ How to create and edit task cards?\n\nAdd cards via the “+” button on top of the list of your choice. Name your card and add a description using the available rich text formatting instruments.\n\nSet a due date to track deadlines and add responsible team members. Label your cards with tags for better navigation. You can create and manage tags in the sidebar, which is accessible via the toolbar button.\n\n### 💡Useful tips for Nextcloud Deck\n\n- Integrate Nextcloud Deck with Nextcloud Calendar and track the upcoming card deadlines.\n- Use comments to keep discussions around a task within its dedicated card.\n- Enable Nextcloud Deck notifications to always stay updated on changes to your boards."),
			"## 🚦 Manage your boards and cards\n\nDrag your cards around the board to change position in the list or move them to other lists.\n\nArchive cards to remove them from the board without losing your content. You can always access archived cards and put them back by going to \"Show archived cards\" in the board menu.\n\nUse tags to assign types, statuses and other special attributes to your cards to aid visual navigation. Open the \"Tags\" tab in the sidebar of Nextcloud Deck to manage tags you can add to your cards.\n\n## 👓 Change how you view your boards\n\nTo navigate large boards easily, you can filter your cards by tags, team members, statuses and due dates. Open filter menu via Nextcloud Deck toolbar.\n\nEnable compact display mode in the Deck Board menu via the three dots menu in the toolbar. In the same menu, you can disable and enable cover images." => $this->l10n->t("## 🚦 Manage your boards and cards\n\nDrag your cards around the board to change position in the list or move them to other lists.\n\nArchive cards to remove them from the board without losing your content. You can always access archived cards and put them back by going to \"Show archived cards\" in the board menu.\n\nUse tags to assign types, statuses and other special attributes to your cards to aid visual navigation. Open the \"Tags\" tab in the sidebar of Nextcloud Deck to manage tags you can add to your cards.\n\n## 👓 Change how you view your boards\n\nTo navigate large boards easily, you can filter your cards by tags, team members, statuses and due dates. Open filter menu via Nextcloud Deck toolbar.\n\nEnable compact display mode in the Deck Board menu via the three dots menu in the toolbar. In the same menu, you can disable and enable cover images."),
			"## 🎨 Make use of rich formatting in card descriptions\n\nYou can use various instruments to make your content more structured and informative. Try all the instruments yourself - use this checklist!\n\n- [ ] Apply headings of different hierarchy (H1-H6).\n- [ ] Use **bold** *italic* __underline__ and ~~strikethrough~~ text formatting.\n- [ ] Create unordered, ordered and to-do lists.\n- [ ] Highlight code and blockquotes, add various illustrated callouts.\n- [ ] Create and format tables\n- [ ] Add details\n- [ ] Add links, for example to [the Nextcloud website](https://nextcloud.com/)\n- [ ] Insert attachments\n- [ ] Add emojis 🕶️ \n\n## 🔗 Attach files to cards\n\nYou can upload any files from your machine or pick from your Nextcloud Files, such as documents, presentations, images, video clips and anything else. Add files via \"Attachments\" tab.\n\n## 🌄 Illustrate cards with images\n\nAttach images to your cards - they will automatically work as card covers." => $this->l10n->t("## 🎨 Make use of rich formatting in card descriptions\n\nYou can use various instruments to make your content more structured and informative. Try all the instruments yourself - use this checklist!\n\n- [ ] Apply headings of different hierarchy (H1-H6).\n- [ ] Use **bold** *italic* __underline__ and ~~strikethrough~~ text formatting.\n- [ ] Create unordered, ordered and to-do lists.\n- [ ] Highlight code and blockquotes, add various illustrated callouts.\n- [ ] Create and format tables\n- [ ] Add details\n- [ ] Add links, for example to [the Nextcloud website](https://nextcloud.com/)\n- [ ] Insert attachments\n- [ ] Add emojis 🕶️ \n\n## 🔗 Attach files to cards\n\nYou can upload any files from your machine or pick from your Nextcloud Files, such as documents, presentations, images, video clips and anything else. Add files via \"Attachments\" tab.\n\n## 🌄 Illustrate cards with images\n\nAttach images to your cards - they will automatically work as card covers."),
			"## 🤝 How to collaborate in your board?\n\nShare your cards and boards with others. In many Nextcloud apps, linked Nextcloud Deck items will appear as rich, interactive widgets. Once you assign someone to a card, they will receive a notification.\n\nEdit cards together, leave notes for your teammates, share task-related files by adding attachments and discuss your tasks using card comments.\n\n### " => $this->l10n->t("## 🤝 How to collaborate in your board?\n\nShare your cards and boards with others. In many Nextcloud apps, linked Nextcloud Deck items will appear as rich, interactive widgets. Once you assign someone to a card, they will receive a notification.\n\nEdit cards together, leave notes for your teammates, share task-related files by adding attachments and discuss your tasks using card comments.\n\n### "),
		];

		foreach ($boardData['addition_labels'] as $labelData) {
			$additionLabels[] = $this->labelService->create(
				$translatedLabelTitles[$labelData['title']] ?? $labelData['title'],
				$labelData['color'],
				$boardId
			);
		}

		$defaultLabels = array_merge($defaultBoard->getLabels() ?? [], $additionLabels);

		foreach ($boardData['stacks'] as $stackData) {
			$stack = $this->stackService->create(
				$translatedStackTitles[$stackData['title']] ?? $stackData['title'],
				$boardId,
				$stackData['order']
			);

			foreach ($stackData['cards'] as $cardData) {
				$card = $this->cardService->create(
					$translatedCardTitles[$cardData['title']] ?? $cardData['title'],
					$stack->getId(),
					$cardData['type'],
					$cardData['order'],
					$userId,
					$translatedCardDescriptions[$cardData['description']] ?? $cardData['description'],
				);

				foreach ($defaultLabels as $defaultLabel) {
					if ($defaultLabel && in_array($defaultLabel->getTitle(), $cardData['labels'], true)) {
						$this->cardService->assignLabel($card->getId(), $defaultLabel->getId());
					}
				}

				if (!empty($cardData['has_example_attachment'])) {
					$this->attachmentService->create($card->getId(), 'file', 'DEFAULT_SAMPLE_FILE');
				}
			}
		}

		return $defaultBoard;
	}
}
