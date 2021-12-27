<?php
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service\Importer\Systems;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class TrelloApiService extends TrelloJsonService {
	/** @var string */
	public static $name = 'Trello API';
	protected $needValidateData = false;
	/** @var IClient */
	private $httpClient;
	/** @var LoggerInterface */
	protected $logger;
	/** @var string */
	private $baseApiUrl = 'https://api.trello.com/1';
	/** @var ?\stdClass[] */
	private $boards;

	public function __construct(
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
		LoggerInterface $logger,
		IClientService $httpClientService
	) {
		parent::__construct($userManager, $urlGenerator, $l10n);
		$this->logger = $logger;
		$this->httpClient = $httpClientService->newClient();
	}

	public function bootstrap(): void {
		$this->populateBoard();
		$this->populateMembers();
		$this->populateLabels();
		$this->populateLists();
		$this->populateCheckLists();
		$this->populateCards();
		$this->populateActions();
		parent::bootstrap();
	}

	public function getJsonSchemaPath(): string {
		return implode(DIRECTORY_SEPARATOR, [
			__DIR__,
			'..',
			'fixtures',
			'config-trelloApi-schema.json',
		]);
	}

	private function populateActions(): void {
		$data = $this->getImportService()->getData();
		$data->actions = $this->doRequest(
			'/boards/' . $data->id . '/actions',
			[
				'filter' => 'commentCard,createCard',
				'fields=memberCreator,type,data,date',
				'memberCreator_fields' => 'username',
				'limit' => 1000
			]
		);
	}

	private function populateCards(): void {
		$data = $this->getImportService()->getData();
		$data->cards = $this->doRequest(
			'/boards/' . $data->id . '/cards',
			[
				'fields' => 'id,idMembers,dateLastActivity,closed,idChecklists,name,idList,pos,desc,due,labels',
				'attachments' => true,
				'attachment_fields' => 'name,url,date',
				'limit' => 1000
			]
		);
	}

	private function populateCheckLists(): void {
		$data = $this->getImportService()->getData();
		$data->checklists = $this->doRequest(
			'/boards/' . $data->id . '/checkLists',
			[
				'fields' => 'id,idCard,name',
				'checkItem_fields' => 'id,state,name',
				'limit' => 1000
			]
		);
	}

	private function populateLists(): void {
		$data = $this->getImportService()->getData();
		$data->lists = $this->doRequest(
			'/boards/' . $data->id . '/lists',
			[
				'fields' => 'id,name,closed',
				'limit' => 1000
			]
		);
	}

	private function populateLabels(): void {
		$data = $this->getImportService()->getData();
		$data->labels = $this->doRequest(
			'/boards/' . $data->id . '/labels',
			[
				'fields' => 'id,color,name',
				'limit' => 1000
			]
		);
	}

	private function populateMembers(): void {
		$data = $this->getImportService()->getData();
		$data->members = $this->doRequest(
			'/boards/' . $data->id . '/members',
			[
				'fields' => 'username',
				'limit' => 1000
			]
		);
	}

	private function populateBoard(): void {
		$toImport = $this->getImportService()->getConfig('board');
		$board = $this->doRequest(
			'/boards/' . $toImport,
			['fields' => 'id,name']
		);
		if ($board instanceof \stdClass) {
			$this->getImportService()->setData($board);
			return;
		}
		throw new \Exception('Invalid board id to import');
	}

	/**
	 * @return array|\stdClass
	 */
	private function doRequest(string $path = '', array $queryString = []) {
		$target = $this->baseApiUrl . $path;
		try {
			$result = $this->httpClient
				->get($target, $this->getQueryString($queryString))
				->getBody();
			if (is_string($result)) {
				$data = json_decode($result);
				if (is_array($data)) {
					$data = array_merge(
						$data,
						$this->paginate($path, $queryString, $data)
					);
				}
				return $data;
			}
			throw new \Exception('Invalid return of api');
		} catch (\Throwable $e) {
			$this->logger->critical(
				$e->getMessage(),
				['app' => 'deck']
			);
			throw new \Exception($e->getMessage());
		}
	}

	private function paginate(string $path = '', array $queryString = [], array $data = []): array {
		if (empty($queryString['limit'])) {
			return [];
		}
		if (count($data) < $queryString['limit']) {
			return [];
		}
		$queryString['before'] = end($data)->id;
		$return = $this->doRequest($path, $queryString);
		if (is_array($return)) {
			return $return;
		}
		throw new \Exception('Invalid return of api');
	}

	private function getQueryString(array $params = []): array {
		$apiSettings = $this->getImportService()->getConfig('api');
		$params['key'] = $apiSettings->key;
		$params['token'] = $apiSettings->token;
		return [
			'query' => $params
		];
	}
}
