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

namespace OCA\Deck\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;

class BoardImportTrelloApiService extends BoardImportTrelloJsonService {
	/** @var string */
	public static $name = 'Trello API';
	protected $needValidateData = false;
	/** @var IClient */
	private $httpClient;
	/** @var ILogger */
	protected $logger;
	/** @var string */
	private $baseApiUrl = 'https://api.trello.com/1';


	public function __construct(
		IUserManager $userManager,
		IL10N $l10n,
		ILogger $logger,
		IClientService $httpClientService
	) {
		parent::__construct($userManager, $l10n);
		$this->logger = $logger;
		$this->httpClient = $httpClientService->newClient();
	}

	public function bootstrap(): void {
		$this->getBoards();
		parent::bootstrap();
	}

	private function getBoards() {
		$boards = $this->doRequest('/members/me/boards');
	}

	private function doRequest($path, $queryString = []) {
		try {
			$target = $this->baseApiUrl . $path;
			$result = $this->httpClient
				->get($target, $this->getQueryString($queryString))
				->getBody();
			$data = json_decode($result);
		} catch (ClientException $e) {
			$status = $e->getCode();
			if ($status === Http::STATUS_FORBIDDEN) {
				$this->logger->info($target . ' refused.', ['app' => 'deck']);
			} else {
				$this->logger->info($target . ' responded with a ' . $status . ' containing: ' . $e->getMessage(), ['app' => 'deck']);
			}
		} catch (RequestException $e) {
			$this->logger->logException($e, [
				'message' => 'Could not connect to ' . $target,
				'level' => ILogger::INFO,
				'app' => 'deck',
			]);
		} catch (\Throwable $e) {
			$this->logger->logException($e, ['app' => 'deck']);
		}
		return $data;
	}

	private function getQueryString($params = []): array {
		$apiSettings = $this->getImportService()->getConfig('api');
		$params['key'] = $apiSettings->key;
		$params['value'] = $apiSettings->token;
		return $params;
	}
}
