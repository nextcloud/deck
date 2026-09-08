<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardExportService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

class DeckMigrator implements IMigrator, ISizeEstimationMigrator {
	use TMigratorBasicVersionHandling;

	protected const FILE_BOARDS = 'boards.json';

	protected const JSON_DEPTH = 512;
	protected const JSON_OPTIONS = JSON_THROW_ON_ERROR;

	public function __construct(
		protected IL10N $l10n,
		protected BoardMapper $boardMapper,
		protected BoardExportService $boardExportService,
		protected BoardService $boardService,
		protected BoardImportService $boardImportService,
		protected PermissionService $permissionService,
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEstimatedExportSize(IUser $user): int|float {
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output,
	): void {
		$uid = $user->getUID();
		$this->boardService->setUserId($uid);
		$this->permissionService->setUserId($uid);

		try {
			$exportData = $this->buildExportData($uid);
			$jsonData = json_encode($exportData, self::JSON_OPTIONS);

			$exportDestination->addFileContents(
				self::FILE_BOARDS,
				$jsonData
			);
		} catch (\Throwable $e) {
			throw new DeckMigratorException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output,
	): void {
		if (!$this->shouldImport($importSource)) {
			return;
		}

		$this->permissionService->setUserId($user->getUID());
		if (!$this->permissionService->canCreate()) {
			$output->writeln('Deck import failed: user is not allowed to create boards.');
			return;
		}

		try {
			$data = $this->readImportData($importSource);
			if (empty($data['boards'])) {
				return;
			}
			$this->configureImportService($user->getUID(), $data);
			$this->boardImportService->import();
		} catch (\Throwable $e) {
			throw new DeckMigratorException($e->getMessage(), 0, $e);
		}
	}

	private function buildExportData(string $uid): array {
		return [
			'boards' => array_values($this->boardExportService->exportBoards(
				$this->boardMapper->findAllByUser($uid),
			)),
		];
	}

	private function shouldImport(IImportSource $importSource): bool {
		return $importSource->getMigratorVersion($this->getId()) !== null;
	}

	private function readImportData(IImportSource $importSource): array {
		$fileContents = $importSource->getFileContents(self::FILE_BOARDS);
		$data = json_decode(
			$fileContents,
			true,
			self::JSON_DEPTH,
			self::JSON_OPTIONS
		);

		if ($data === null) {
			throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
		}

		return $data;
	}

	private function configureImportService(string $userId, array $data): void {
		$this->boardImportService->setSystem('DeckJson');
		$this->boardImportService->setConfigInstance((object)[
			'owner' => $userId,
			'uidRelation' => new \stdClass(),
		]);
		$this->boardImportService->setData(json_decode(
			json_encode(['boards' => $data['boards']]),
			false,
			self::JSON_DEPTH,
			self::JSON_OPTIONS
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string {
		return 'deck';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Deck');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string {
		return $this->l10n->t('All boards owned by you including stacks, cards, labels, assignments, and comments');
	}
}
