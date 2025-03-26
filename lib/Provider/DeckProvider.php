<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Provider;

use OC\FullTextSearch\Model\IndexDocument;
use OC\FullTextSearch\Model\SearchTemplate;
use OCA\Deck\Service\FullTextSearchService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\FullTextSearch\IFullTextSearchPlatform;
use OCP\FullTextSearch\IFullTextSearchProvider;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IIndexOptions;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Model\ISearchTemplate;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Class DeckProvider
 *
 * @package OCA\Deck\Provider
 */
class DeckProvider implements IFullTextSearchProvider {
	public const DECK_PROVIDER_ID = 'deck';

	private IL10N $l10n;
	private IUrlGenerator $urlGenerator;
	private FullTextSearchService $fullTextSearchService;
	private ?IRunner $runner = null;
	private ?IIndexOptions $indexOptions = null;


	/**
	 * DeckProvider constructor.
	 *
	 * @param IL10N $l10n
	 * @param IUrlGenerator $urlGenerator
	 * @param FullTextSearchService $fullTextSearchService
	 */
	public function __construct(
		IL10N $l10n, IUrlGenerator $urlGenerator, FullTextSearchService $fullTextSearchService,
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->fullTextSearchService = $fullTextSearchService;
	}


	/**
	 * return unique id of the provider
	 */
	public function getId(): string {
		return self::DECK_PROVIDER_ID;
	}


	/**
	 * return name of the provider
	 */
	public function getName(): string {
		return 'Deck';
	}


	/**
	 * @return array
	 */
	public function getConfiguration(): array {
		return [];
	}


	/**
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner) {
		$this->runner = $runner;
	}


	/**
	 * @param IIndexOptions $options
	 */
	public function setIndexOptions(IIndexOptions $options) {
		$this->indexOptions = $options;
	}


	public function getSearchTemplate(): ISearchTemplate {
		/** @psalm-var ISearchTemplate */
		$template = new SearchTemplate('icon-deck', 'icons');

		return $template;
	}


	/**
	 *
	 */
	public function loadProvider() {
	}



	/**
	 * @param string $userId
	 *
	 * @return string[]
	 */
	public function generateChunks(string $userId): array {
		return [];
	}


	/**
	 * @param string $userId
	 * @param string $chunk
	 *
	 * @return IIndexDocument[]
	 */
	public function generateIndexableDocuments(string $userId, string $chunk): array {
		$cards = $this->fullTextSearchService->getCardsFromUser($userId);

		$documents = [];
		foreach ($cards as $card) {
			$documents[] = $this->fullTextSearchService->generateIndexDocumentFromCard($card);
		}

		return $documents;
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function fillIndexDocument(IIndexDocument $document) {
		$this->fullTextSearchService->fillIndexDocument($document);
		$this->updateRunnerInfo('info', $document->getTitle());
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool {
		return false;
	}


	/**
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function updateDocument(IIndex $index): IIndexDocument {
		/** @psalm-var IIndexDocument */
		$document = new IndexDocument(DeckProvider::DECK_PROVIDER_ID, $index->getDocumentId());
		$document->setIndex($index);

		$this->fullTextSearchService->fillIndexDocument($document);

		return $document;
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onInitializingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onResettingIndex(IFullTextSearchPlatform $platform) {
	}


	/**
	 * not used yet
	 */
	public function unloadProvider() {
	}


	/**
	 * before a search, improve the request
	 *
	 * @param ISearchRequest $request
	 */
	public function improveSearchRequest(ISearchRequest $searchRequest) {
	}


	/**
	 * after a search, improve results
	 *
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult) {
		foreach ($searchResult->getDocuments() as $document) {
			try {
				$board =
					$this->fullTextSearchService->getBoardFromCardId((int)$document->getId());
				$path = '/board/' . $board->getId() . '/card/' . $document->getId();
				$document->setLink($this->urlGenerator->linkToRoute('deck.page.index') . $path);
			} catch (DoesNotExistException $e) {
			} catch (MultipleObjectsReturnedException $e) {
			}
		}
	}


	/**
	 * @param string $info
	 * @param string $value
	 */
	private function updateRunnerInfo(string $info, string $value) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->setInfo($info, $value);
	}
}
