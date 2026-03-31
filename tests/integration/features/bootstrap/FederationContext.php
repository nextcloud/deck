<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation test context for Deck.
 *
 * Uses OCS API with Basic Auth exclusively (no cookie-based auth) to avoid
 * session issues with PHP's built-in web server across two instances.
 */
class FederationContext implements Context {
	private string $localServerUrl;
	private string $remoteServerUrl;
	private string $currentServer = 'LOCAL';

	private string $localOccPath;
	private string $remoteOccPath;
	private string $remoteConfigDir;

	private string $currentUser = 'admin';
	private ?ResponseInterface $response = null;

	/** @var array|null Last board created/fetched */
	private ?array $board = null;
	/** @var array|null Last stack created/fetched */
	private ?array $stack = null;
	/** @var array|null Last card created/fetched */
	private ?array $card = null;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$this->localServerUrl = rtrim(getenv('TEST_SERVER_URL') ?: 'http://localhost:8080/', '/');
		$this->remoteServerUrl = rtrim(getenv('TEST_REMOTE_URL') ?: 'http://localhost:8280/', '/');
		$this->localOccPath = getenv('NEXTCLOUD_HOST_ROOT_DIR') ?: '';
		$this->remoteOccPath = getenv('NEXTCLOUD_REMOTE_ROOT_DIR') ?: '';
		$this->remoteConfigDir = getenv('NEXTCLOUD_REMOTE_CONFIG_DIR') ?: '';
	}

	private function getServerUrl(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		return $server === 'REMOTE' ? $this->remoteServerUrl : $this->localServerUrl;
	}

	private function getOccPath(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		return $server === 'REMOTE' ? $this->remoteOccPath : $this->localOccPath;
	}

	private function getOccEnvPrefix(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		if ($server === 'REMOTE' && !empty($this->remoteConfigDir)) {
			return 'NEXTCLOUD_CONFIG_DIR=' . escapeshellarg($this->remoteConfigDir) . ' ';
		}
		return '';
	}

	private function getPassword(string $user): string {
		return ($user === 'admin') ? 'admin' : '123456';
	}

	/**
	 * Send an OCS request with Basic Auth to the current (or specified) server.
	 */
	private function sendOCSRequest(string $method, string $path, array $data = [], ?string $user = null, ?string $server = null): ResponseInterface {
		$user = $user ?? $this->currentUser;
		$baseUrl = $this->getServerUrl($server);
		$url = $baseUrl . '/ocs/v2.php/' . ltrim($path, '/');

		$client = new Client();
		$options = [
			'auth' => [$user, $this->getPassword($user)],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			],
		];
		if (!empty($data)) {
			$options['json'] = $data;
		}

		try {
			$this->response = $client->request($method, $url, $options);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}

		return $this->response;
	}

	private function getOCSData(): array {
		$this->response->getBody()->seek(0);
		$json = json_decode((string)$this->response->getBody(), true);
		return $json['ocs']['data'] ?? [];
	}

	// ---- Step definitions ----

	/**
	 * @Given /^using server "([^"]*)"$/
	 */
	public function usingServer(string $server) {
		$server = strtoupper($server);
		if (!in_array($server, ['LOCAL', 'REMOTE'])) {
			throw new \InvalidArgumentException('Server must be LOCAL or REMOTE');
		}
		$this->currentServer = $server;
	}

	/**
	 * @Given /^acting as user "([^"]*)"$/
	 */
	public function actingAsUser(string $user) {
		$this->currentUser = $user;
	}

	/**
	 * @Given /^federation is enabled on "([^"]*)"$/
	 */
	public function federationIsEnabledOn(string $server) {
		$occPath = $this->getOccPath($server);
		if (empty($occPath)) {
			throw new \RuntimeException("OCC path not configured for server $server");
		}
		$envPrefix = $this->getOccEnvPrefix($server);
		exec("{$envPrefix}php {$occPath}/occ config:app:set deck federationEnabled --value=yes 2>&1", $output, $returnCode);
		if ($returnCode !== 0) {
			throw new \RuntimeException("Failed to enable federation on {$server}: " . implode("\n", $output));
		}
		// Also enable server-to-server sharing which is required by ensureFederationEnabled()
		exec("{$envPrefix}php {$occPath}/occ config:app:set files_sharing outgoing_server2server_share_enabled --value=yes 2>&1");
		exec("{$envPrefix}php {$occPath}/occ config:app:set files_sharing incoming_server2server_share_enabled --value=yes 2>&1");
	}

	/**
	 * @Given /^user "([^"]*)" exists on "([^"]*)"$/
	 */
	public function userExistsOn(string $user, string $server) {
		$occPath = $this->getOccPath($server);
		if (empty($occPath)) {
			throw new \RuntimeException("OCC path not configured for server $server");
		}

		$envPrefix = $this->getOccEnvPrefix($server);
		$password = $this->getPassword($user);
		exec("{$envPrefix}OC_PASS={$password} php {$occPath}/occ user:add --password-from-env {$user} 2>&1", $output, $returnCode);
		if ($returnCode !== 0) {
			// User already exists — reset password to ensure it matches
			exec("{$envPrefix}OC_PASS={$password} php {$occPath}/occ user:resetpassword --password-from-env {$user} 2>&1");
		}
	}

	/**
	 * @Given /^creates a board named "([^"]*)" with color "([^"]*)"$/
	 */
	public function createsABoardNamedWithColor(string $title, string $color) {
		$this->sendOCSRequest('POST', '/apps/deck/api/v1.0/boards', [
			'title' => $title,
			'color' => $color,
		]);
		$this->board = $this->getOCSData();
		Assert::assertArrayHasKey('id', $this->board, 'Board creation failed: ' . (string)$this->response->getBody());
	}

	/**
	 * @Given /^create a stack named "([^"]*)"$/
	 */
	public function createAStackNamed(string $name) {
		Assert::assertNotNull($this->board, 'No board created yet');
		$this->sendOCSRequest('POST', '/apps/deck/api/v1.0/stacks', [
			'title' => $name,
			'boardId' => $this->board['id'],
			'order' => 0,
		]);
		$this->stack = $this->getOCSData();
		Assert::assertArrayHasKey('id', $this->stack, 'Stack creation failed: ' . (string)$this->response->getBody());
	}

	/**
	 * @When /^user "([^"]*)" on "([^"]*)" shares the board with federated user "([^"]*)"$/
	 */
	public function userSharesBoardWithFederatedUser(string $user, string $server, string $remoteUser, ?TableNode $permissions = null) {
		$remoteServerUrl = ($server === 'LOCAL') ? $this->remoteServerUrl : $this->localServerUrl;
		$federatedUserId = $remoteUser . '@' . $remoteServerUrl;

		$defaults = [
			'permissionEdit' => '0',
			'permissionShare' => '0',
			'permissionManage' => '0',
		];
		$tableRows = isset($permissions) ? $permissions->getRowsHash() : [];
		$result = array_merge($defaults, $tableRows);

		Assert::assertNotNull($this->board, 'No board created yet');
		$this->sendOCSRequest('POST', '/apps/deck/api/v1.0/boards/' . $this->board['id'] . '/acl', [
			'type' => 6, // Acl::PERMISSION_TYPE_REMOTE
			'participant' => $federatedUserId,
			'permissionEdit' => $result['permissionEdit'] === '1',
			'permissionShare' => $result['permissionShare'] === '1',
			'permissionManage' => $result['permissionManage'] === '1',
		], $user, $server);
	}

	/**
	 * @Then /^user "([^"]*)" on "([^"]*)" should see the board "([^"]*)"$/
	 */
	public function userOnShouldSeeBoard(string $user, string $server, string $boardTitle) {
		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/boards', [], $user, $server);
		$boards = $this->getOCSData();

		$found = false;
		foreach ($boards as $board) {
			if ($board['title'] === $boardTitle) {
				$found = true;
				break;
			}
		}

		Assert::assertTrue($found, "Board '{$boardTitle}' not found for user '{$user}' on {$server}");
	}

	/**
	 * @Then /^user "([^"]*)" on "([^"]*)" should not see the board "([^"]*)"$/
	 */
	public function userOnShouldNotSeeBoard(string $user, string $server, string $boardTitle) {
		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/boards', [], $user, $server);
		$boards = $this->getOCSData();

		$found = false;
		foreach ($boards as $board) {
			if ($board['title'] === $boardTitle) {
				$found = true;
				break;
			}
		}

		Assert::assertFalse($found, "Board '{$boardTitle}' should not be visible for user '{$user}' on {$server}");
	}

	/**
	 * @Then /^user "([^"]*)" on "([^"]*)" should see assigned user "([^"]*)" on card "([^"]*)" on the federated board "([^"]*)"$/
	 */
	public function userOnShouldSeeAssigned(string $user, string $server, string $assignedUser, string $cardTitle, string $boardTitle) {
		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/boards', [], $user, $server);
		$boards = $this->getOCSData();

		$found = false;
		foreach ($boards as $board) {
			if ($board['title'] === $boardTitle) {
				$found = $board;
				break;
			}
		}

		Assert::assertNotNull($found, "Board '{$boardTitle}' not found for user '{$user}' on {$server}");

		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/stacks/' . $found['id'], [], $user, $server);
		$stacks = $this->getOCSData();
		$cardTitleFound = false;
		$cardFound = false;
		$assignedUsers = [];
		foreach ($stacks as $stack) {
			foreach ($stack['cards'] as $card) {
				if ($card['title'] === $cardTitle) {
					$cardTitleFound = true;
					foreach ($card['assignedUsers'] as $assigned) {
						$assignedUsers[] = $assigned;
						if ($assigned['participant']['displayname'] === $assignedUser) {
							$cardFound = true;
							break 3;
						}
					}
				}
			}
		}

		Assert::assertTrue($cardTitleFound, "Card '{$cardTitle}' not found on board '{$boardTitle}'");

		Assert::assertTrue($cardFound, "Assigned user '{$assignedUser}' not found on card '{$cardTitle}' on board '{$boardTitle}' found '" . json_encode($assignedUsers) . "'");
	}



	/**
	 * @When /^user "([^"]*)" on "([^"]*)" creates a stack "([^"]*)" on the federated board$/
	 */
	public function userCreatesStackOnFederatedBoard(string $user, string $server, string $stackTitle) {
		$federatedBoard = $this->findFederatedBoard($user, $server);

		$this->sendOCSRequest('POST', '/apps/deck/api/v1.0/stacks', [
			'title' => $stackTitle,
			'boardId' => $federatedBoard['id'],
			'order' => 0,
		], $user, $server);
	}

	/**
	 * @When /^user "([^"]*)" on "([^"]*)" creates a card "([^"]*)" on stack "([^"]*)" on the federated board$/
	 */
	public function userCreatesCardOnFederatedBoard(string $user, string $server, string $cardTitle, string $stackTitle) {
		$federatedBoard = $this->findFederatedBoard($user, $server);

		// Get stacks to find the right one
		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/stacks/' . $federatedBoard['id'], [], $user, $server);
		$stacks = $this->getOCSData();

		$stackId = null;
		foreach ($stacks as $stack) {
			if ($stack['title'] === $stackTitle) {
				$stackId = $stack['id'];
				break;
			}
		}

		Assert::assertNotNull($stackId, "Stack '{$stackTitle}' not found on federated board");

		$this->sendOCSRequest('POST', '/apps/deck/api/v1.0/cards', [
			'title' => $cardTitle,
			'stackId' => $stackId,
			'boardId' => $federatedBoard['id'],
		], $user, $server);
		$this->card = $this->getOCSData();
	}

	/**
	 * @Then /^the OCS response should have status code "([^"]*)"$/
	 */
	public function theOcsResponseShouldHaveStatusCode(string $code) {
		$currentCode = $this->response->getStatusCode();
		Assert::assertEquals((int)$code, $currentCode, "Expected status code {$code} but got {$currentCode}");
	}

	/**
	 * @Then /^user "([^"]*)" on "([^"]*)" should see (\d+) stacks? on the board "([^"]*)"$/
	 */
	public function userShouldSeeStacksOnBoard(string $user, string $server, int $count, string $boardTitle) {
		// Find the most recently created board with the given title
		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/boards', [], $user, $server);
		$boards = $this->getOCSData();

		$boardId = null;
		foreach ($boards as $board) {
			if ($board['title'] === $boardTitle) {
				if ($boardId === null || $board['id'] > $boardId) {
					$boardId = $board['id'];
				}
			}
		}
		Assert::assertNotNull($boardId, "Board '{$boardTitle}' not found");

		$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/stacks/' . $boardId, [], $user, $server);
		$stacks = $this->getOCSData();

		Assert::assertCount($count, $stacks, "Expected {$count} stacks but got " . count($stacks));
	}

	private function findFederatedBoard(string $user, string $server): array {
		Assert::assertNotNull($this->board, 'No board created in this scenario');
		$expectedTitle = $this->board['title'];

		// Federation shares may take a moment to propagate, retry a few times
		for ($i = 0; $i < 10; $i++) {
			$this->sendOCSRequest('GET', '/apps/deck/api/v1.0/boards', [], $user, $server);
			$boards = $this->getOCSData();

			// Find the most recently created federated board with the expected title
			$match = null;
			foreach ($boards as $board) {
				if (!empty($board['externalId']) && $board['title'] === $expectedTitle) {
					if ($match === null || $board['id'] > $match['id']) {
						$match = $board;
					}
				}
			}

			if ($match !== null) {
				return $match;
			}

			usleep(500000); // 500ms
		}

		throw new \RuntimeException('No federated board "' . $expectedTitle . '" found for user ' . $user . ' on ' . $server);
	}

	/**
	 * @When /^user "([^"]*)" on "([^"]*)" assigns user "([^"]*)" to card "([^"]*)" on the federated board$/
	 *
	 * Assign a user to a card by card title on the federated board.
	 *
	 * @param string $user    The acting user
	 * @param string $server  LOCAL or REMOTE
	 * @param string $userId  The user id to assign
	 * @param string $cardTitle The card title
	 */
	public function assignUserToCard(string $user, string $server, string $userId, string $cardTitle) {
		$federatedBoard = $this->findFederatedBoard($user, $server);
		Assert::assertNotNull($this->card, 'No card created in this scenario');
		Assert::assertEquals($cardTitle, $this->card['title'], 'Last card title does not match');
		$cardId = $this->card['id'];
		$data = [
			'userId' => $userId,
			'type' => 0,
		];
		$this->sendOCSRequest('POST', "/apps/deck/api/v1.0/cards/{$cardId}/assign?boardId={$federatedBoard['id']}", $data, $user, $server);
		Assert::assertEquals(200, $this->response->getStatusCode(), "Failed to assign user '{$userId}' to card '{$cardTitle}' on federated board: " . (string)$this->response->getBody());
	}

	/**
	 * Unassign a user from a card using the CardOcsController OCS route.
	 *
	 * @param string $user    The acting user
	 * @param string $server  LOCAL or REMOTE
	 * @param int    $cardId  The card id
	 * @param string $userId  The user id to unassign
	 * @param int|null $boardId The board id (required for federated boards)
	 * @param int    $type    The assignment type (default 0)
	 */
	public function unassignUserFromCard(string $user, string $server, int $cardId, string $userId, ?int $boardId = null, int $type = 0) {
		$data = [
			'userId' => $userId,
			'type' => $type,
		];
		if ($boardId !== null) {
			$data['boardId'] = $boardId;
		}
		$this->sendOCSRequest('POST', "/apps/deck/api/v1.0/cards/{$cardId}/unassign", $data, $user, $server);
	}
}
