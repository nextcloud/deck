<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class BoardContext implements Context {
	use RequestTrait;

	/** @var array Last board response */
	private $board = null;
	/** @var array last stack response */
	private $stack = null;
	/** @var array last card response */
	private $card = null;
	private array $storedCards = [];
	private array $storedStacks = [];
	private ?array $activities = null;

	/** @var int[] Board IDs created during the current scenario */
	private array $createdBoardIds = [];

	private ServerContext $serverContext;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
	}

	/** @AfterScenario */
	public function cleanupBoards(AfterScenarioScope $scope): void {
		foreach ($this->createdBoardIds as $boardId) {
			try {
				$this->requestContext->sendJSONrequest('DELETE', '/index.php/apps/deck/boards/' . $boardId);
			} catch (\Exception $e) {
				// Ignore cleanup errors
			}
		}
		$this->createdBoardIds = [];
	}

	private function trackBoard(?array $board): void {
		if (is_array($board) && isset($board['id'])) {
			$this->createdBoardIds[] = $board['id'];
		}
	}

	public function getLastUsedCard() {
		return $this->card;
	}

	public function getLastUsedBoard() {
		return $this->board;
	}

	/**
	 * @Given /^creates a board with example content$/
	 */
	public function createExampleContent() {
		$this->createsABoardNamedWithColor('Example board', 'ff0000');
		$this->createAStackNamed('ToDo');
		$this->createACardNamed('My example card');
	}

	/**
	 * @Given /^creates a board named "([^"]*)" with color "([^"]*)"$/
	 */
	public function createsABoardNamedWithColor($title, $color) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards', [
			'title' => $title,
			'color' => $color
		]);
		$this->getResponse()->getBody()->seek(0);
		$this->board = json_decode((string)$this->getResponse()->getBody(), true);
		$this->trackBoard($this->board);
	}

	/**
	 * @When /^fetches the board named "([^"]*)"$/
	 */
	public function fetchesTheBoardNamed($boardName) {
		$id = null;
		if (!$this->board || $boardName != $this->board['title']) {
			$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards', []);
			$boards = json_decode((string)$this->getResponse()->getBody(), true);
			foreach (array_reverse($boards) as $board) {
				if ($board['title'] == $boardName) {
					$id = $board['id'];
					break;
				}
			}
			Assert::assertNotNull($id, 'Could not find board named ' . $boardName);
		} else {
			$id = $this->board['id'];
		}
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $id, []);
		$this->getResponse()->getBody()->seek(0);
		$this->board = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @When shares the board with user :user
	 */
	public function sharesTheBoardWithUser($user, ?TableNode $permissions = null) {
		$defaults = [
			'permissionEdit' => '0',
			'permissionShare' => '0',
			'permissionManage' => '0'
		];
		$tableRows = isset($permissions) ? $permissions->getRowsHash() : [];
		$result = array_merge($defaults, $tableRows);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
			'type' => 0,
			'participant' => $user,
			'permissionEdit' => $result['permissionEdit'] === '1',
			'permissionShare' => $result['permissionShare'] === '1',
			'permissionManage' => $result['permissionManage'] === '1',
		]);
	}

	/**
	 * @When shares the board with group :group
	 */
	public function sharesTheBoardWithGroup($group, ?TableNode $permissions = null) {
		$defaults = [
			'permissionEdit' => '0',
			'permissionShare' => '0',
			'permissionManage' => '0'
		];
		$tableRows = isset($permissions) ? $permissions->getRowsHash() : [];
		$result = array_merge($defaults, $tableRows);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
			'type' => 1,
			'participant' => $group,
			'permissionEdit' => $result['permissionEdit'] === '1',
			'permissionShare' => $result['permissionShare'] === '1',
			'permissionManage' => $result['permissionManage'] === '1',
		]);
	}


	/**
	 * @When /^fetching the board list$/
	 */
	public function fetchingTheBoardList() {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards');
	}

	/**
	 * @When /^fetching the board with id "([^"]*)"$/
	 */
	public function fetchingTheBoardWithId($id) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $id);
	}

	/**
	 * @Given /^create a stack named "([^"]*)"$/
	 */
	public function createAStackNamed($name) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/stacks', [
			'title' => $name,
			'boardId' => $this->board['id']
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->stack = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Given /^create a card named "([^"]*)"$/
	 */
	public function createACardNamed($name) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards', [
			'title' => $name,
			'stackId' => $this->stack['id']
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->card = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Then /^the current user should have "(read|edit|share|manage)" permissions on the board$/
	 */
	public function theCurrentUserShouldHavePermissionsOnTheBoard($permission) {
		Assert::assertTrue($this->getPermissionsValue($permission));
	}

	/**
	 * @Then /^the current user should not have "(read|edit|share|manage)" permissions on the board$/
	 */
	public function theCurrentUserShouldNotHavePermissionsOnTheBoard($permission) {
		Assert::assertFalse($this->getPermissionsValue($permission));
	}

	private function getPermissionsValue($permission) {
		$mapping = [
			'read' => 'PERMISSION_READ',
			'edit' => 'PERMISSION_EDIT',
			'share' => 'PERMISSION_SHARE',
			'manage' => 'PERMISSION_MANAGE',
		];
		return $this->board['permissions'][$mapping[$permission]];
	}

	/**
	 * @When /^share the file "([^"]*)" with the card$/
	 */
	public function shareWithTheCard($file) {
		$table = new TableNode([
			['path', $file],
			['shareType', 12],
			['shareWith', (string)$this->card['id']],
		]);
		$this->serverContext->creatingShare($table);
	}

	/**
	 * @Given /^set the description to "([^"]*)"$/
	 */
	public function setTheDescriptionTo($description) {
		$this->requestContext->sendJSONrequest('PUT', '/index.php/apps/deck/cards/' . $this->card['id'], array_merge(
			$this->card,
			['description' => $description]
		));
		$this->requestContext->getResponse()->getBody()->seek(0);
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->card = json_decode((string)$this->getResponse()->getBody(), true);
		}
	}

	/**
	 * @Given /^set the card attribute "([^"]*)" to "([^"]*)"$/
	 */
	public function setCardAttribute($attribute, $value) {
		$this->requestContext->sendJSONrequest('PUT', '/index.php/apps/deck/cards/' . $this->card['id'], array_merge(
			$this->card,
			[$attribute => $value]
		));
		$this->requestContext->getResponse()->getBody()->seek(0);
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->card = json_decode((string)$this->getResponse()->getBody(), true);
		}
	}

	/**
	 * @Given /^get the card details$/
	 */
	public function getCard() {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/cards/' . $this->card['id'], array_merge(
			$this->card
		));
		$this->requestContext->getResponse()->getBody()->seek(0);
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->card = json_decode((string)$this->getResponse()->getBody(), true);
		}
	}

	/**
	 * @Given /^set the card duedate to "([^"]*)"$/
	 */
	public function setTheCardDuedateTo($arg1) {
		$date = new DateTime($arg1);
		$this->setCardAttribute('duedate', $date->format(DateTimeInterface::ATOM));
	}

	/**
	 * @Given /^assign the card to the user "([^"]*)"$/
	 */
	public function assignTheCardToTheUser($user) {
		$this->assignToCard($user, 0);
	}

	/**
	 * @Given /^assign the card to the group "([^"]*)"$/
	 */
	public function assignTheCardToTheGroup($user) {
		$this->assignToCard($user, 1);
	}

	private function assignToCard($participant, $type) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards/' . $this->card['id'] . '/assign', [
			'userId' => $participant,
			'type' => $type
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
	}

	/**
	 * @Given /^assign the tag "([^"]*)" to the card$/
	 */
	public function assignTheTagToTheCard($tag) {
		$filteredLabels = array_filter($this->board['labels'], function ($label) use ($tag) {
			return $label['title'] === $tag;
		});
		$label = array_shift($filteredLabels);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards/' . $this->card['id'] . '/label/' . $label['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
	}

	/**
	 * @When remember the last card as :arg1
	 */
	public function rememberTheLastCardAs($arg1) {
		$this->storedCards[$arg1] = $this->getLastUsedCard();
	}

	public function getRememberedCard($arg1) {
		return $this->storedCards[$arg1] ?? null;
	}

	/**
	 * @Given /^delete the card$/
	 */
	public function deleteTheCard() {
		$this->requestContext->sendJSONrequest('DELETE', '/index.php/apps/deck/cards/' . $this->card['id']);
		$this->card['deletedAt'] = time();
	}

	/**
	 * @Given /^delete the board/
	 */
	public function deleteTheBoard() {
		$this->requestContext->sendJSONrequest('DELETE', '/index.php/apps/deck/boards/' . $this->board['id']);
	}


	/**
	 * @Given /^get the activities for the last card$/
	 */
	public function getActivitiesForTheLastCard() {
		$card = $this->getLastUsedCard();
		$this->requestContext->sendOCSRequest('GET', '/apps/activity/api/v2/activity/filter?format=json&type=deck&since=0&object_type=deck_card&object_id=' . $card['id'] . '&limit=50');
		$this->activities = json_decode((string)$this->getResponse()->getBody(), true)['ocs']['data'] ?? null;
	}

	/**
	 * @Then the fetched activities should have :count entries
	 */
	public function theFetchedActivitiesShouldHaveEntries($count) {
		Assert::assertEquals($count, count($this->activities ?? []));
	}

	/**
	 * @When remember the last stack as :name
	 */
	public function rememberTheLastStackAs($name) {
		$this->storedStacks[$name] = $this->stack;
	}

	/**
	 * @When /^sets the current stack as done column$/
	 */
	public function setsTheCurrentStackAsDoneColumn() {
		$this->requestContext->sendOCSRequest(
			'PUT',
			'apps/deck/api/v1.0/stacks/' . $this->stack['id'] . '/done',
			['boardId' => $this->board['id'], 'isDone' => true]
		);
	}

	/**
	 * @When /^unsets the current stack as done column$/
	 */
	public function unsetsTheCurrentStackAsDoneColumn() {
		$this->requestContext->sendOCSRequest(
			'PUT',
			'apps/deck/api/v1.0/stacks/' . $this->stack['id'] . '/done',
			['boardId' => $this->board['id'], 'isDone' => false]
		);
	}

	/**
	 * @When /^move the card to the stack "([^"]*)"$/
	 */
	public function moveTheCardToTheStack($stackName) {
		$stack = $this->storedStacks[$stackName] ?? null;
		Assert::assertNotNull($stack, 'Stack "' . $stackName . '" not found in stored stacks');
		$this->requestContext->sendJSONrequest(
			'PUT',
			'/index.php/apps/deck/cards/' . $this->card['id'] . '/reorder',
			['stackId' => $stack['id'], 'order' => 0]
		);
		// Reload the card so subsequent assertions see the updated done status
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->getCard();
		}
	}

	/**
	 * @When /^mark the card as done$/
	 */
	public function markTheCardAsDone() {
		$this->requestContext->sendJSONrequest(
			'PUT',
			'/index.php/apps/deck/cards/' . $this->card['id'] . '/done'
		);
		$this->requestContext->getResponse()->getBody()->seek(0);
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->card = json_decode((string)$this->getResponse()->getBody(), true);
		}
	}

	/**
	 * @Given /^the board is archived$/
	 */
	public function theBoardIsArchived() {
		$this->requestContext->sendJSONrequest(
			'PUT',
			'/index.php/apps/deck/boards/' . $this->board['id'],
			array_merge($this->board, ['archived' => true])
		);
		$this->requestContext->getResponse()->getBody()->seek(0);
		if ($this->requestContext->getResponse()->getStatusCode() === 200) {
			$this->board = json_decode((string)$this->getResponse()->getBody(), true);
		}
	}

	/**
	 * @Then /^the card should be marked as done$/
	 */
	public function theCardShouldBeMarkedAsDone() {
		Assert::assertNotEmpty($this->card['done'], 'Expected card to be marked as done, but done is empty');
	}

	/**
	 * @Then /^the card should not be marked as done$/
	 */
	public function theCardShouldNotBeMarkedAsDone() {
		Assert::assertEmpty($this->card['done'], 'Expected card not to be marked as done, but done is: ' . print_r($this->card['done'], true));
	}

	/**
	 * @Then /^the current stack should be marked as done column$/
	 */
	public function theCurrentStackShouldBeMarkedAsDoneColumn() {
		$found = $this->getStack();
		Assert::assertTrue($found['isDoneColumn'], 'Expected current stack to be marked as done column');
	}

	/**
	 * @Then /^the current stack should not be marked as done column$/
	 */
	public function theCurrentStackShouldNotBeMarkedAsDoneColumn() {
		$found = $this->getStack();
		Assert::assertFalse($found['isDoneColumn'], 'Expected current stack not to be marked as done column');
	}

	/**
	 * @Then /^the remembered card "([^"]*)" should be marked as done$/
	 */
	public function theRememberedCardShouldBeMarkedAsDone($name) {
		$card = $this->storedCards[$name] ?? null;
		Assert::assertNotNull($card, 'Card "' . $name . '" not found in stored cards');
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/cards/' . $card['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$freshCard = json_decode((string)$this->getResponse()->getBody(), true);
		Assert::assertNotEmpty($freshCard['done'], 'Expected remembered card "' . $name . '" to be marked as done, but done is empty');
	}

	/**
	 * @Then /^the card should be in the stack "([^"]*)"$/
	 */
	public function theCardShouldBeInTheStack($stackName) {
		$stack = $this->storedStacks[$stackName] ?? null;
		Assert::assertNotNull($stack, 'Stack "' . $stackName . '" not found in stored stacks');
		Assert::assertEquals(
			$stack['id'],
			$this->card['stackId'],
			'Expected card to be in stack "' . $stackName . '" (id: ' . $stack['id'] . '), but it is in stack id: ' . $this->card['stackId']
		);
	}

	/**
	 * @return mixed|null
	 */
	private function getStack(): mixed {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/stacks/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$stacks = json_decode((string)$this->getResponse()->getBody(), true);
		$found = null;
		foreach ($stacks as $stack) {
			if ($stack['id'] === $this->stack['id']) {
				$found = $stack;
				break;
			}
		}
		Assert::assertNotNull($found, 'Current stack not found in board stacks');
		return $found;
	}

	/**
	 * @When /^importing a board from CSV file with content$/
	 */
	public function importingABoardFromCsvFileWithContent(\Behat\Gherkin\Node\PyStringNode $content) {
		$csvContent = $content->getRaw();
		$this->requestContext->sendPlainRequest('POST', '/index.php/apps/deck/boards/import', [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $csvContent,
					'filename' => 'import.csv',
					'headers' => ['Content-Type' => 'text/csv'],
				],
			],
		]);
		$this->getResponse()->getBody()->seek(0);
		$body = json_decode((string)$this->getResponse()->getBody(), true);
		if ($this->getResponse()->getStatusCode() === 200 && is_array($body) && isset($body['board'])) {
			$this->board = $body['board'];
			$this->trackBoard($this->board);
		}
	}

	/**
	 * @When /^importing CSV cards into the current board with content$/
	 */
	public function importingCsvCardsIntoTheCurrentBoardWithContent(\Behat\Gherkin\Node\PyStringNode $content) {
		Assert::assertNotNull($this->board, 'Board must be created first');
		$csvContent = $content->getRaw();
		// Allow tests to reference the last created card's ID via {lastCardId}
		if ($this->card !== null && isset($this->card['id'])) {
			$csvContent = str_replace('{lastCardId}', (string)$this->card['id'], $csvContent);
		}
		$this->requestContext->sendPlainRequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/importCsv', [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $csvContent,
					'filename' => 'cards.csv',
					'headers' => ['Content-Type' => 'text/csv'],
				],
			],
		]);
		$this->getResponse()->getBody()->seek(0);
		$body = json_decode((string)$this->getResponse()->getBody(), true);
		if ($this->getResponse()->getStatusCode() === 200 && is_array($body) && isset($body['board'])) {
			$this->board = $body['board'];
		}
	}

	/**
	 * @When /^importing a non-CSV file into the current board$/
	 */
	public function importingANonCsvFileIntoTheCurrentBoard() {
		Assert::assertNotNull($this->board, 'Board must be created first');
		$this->requestContext->sendPlainRequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/importCsv', [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => '{"not": "csv"}',
					'filename' => 'data.json',
					'headers' => ['Content-Type' => 'application/json'],
				],
			],
		]);
	}

	/**
	 * @Then /^the board should have (\d+) stacks$/
	 */
	public function theBoardShouldHaveStacks($count) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/stacks/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$stacks = json_decode((string)$this->getResponse()->getBody(), true);
		Assert::assertCount((int)$count, $stacks, 'Expected ' . $count . ' stacks, got ' . count($stacks));
	}

	/**
	 * @Then /^the board should have a stack named "([^"]*)"$/
	 */
	public function theBoardShouldHaveAStackNamed($name) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/stacks/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$stacks = json_decode((string)$this->getResponse()->getBody(), true);
		$found = array_filter($stacks, fn ($s) => $s['title'] === $name);
		Assert::assertNotEmpty($found, 'Stack "' . $name . '" not found on board');
	}

	/**
	 * @Then /^the stack "([^"]*)" should have (\d+) cards$/
	 */
	public function theStackShouldHaveCards($stackName, $count) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/stacks/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$stacks = json_decode((string)$this->getResponse()->getBody(), true);
		$found = null;
		foreach ($stacks as $stack) {
			if ($stack['title'] === $stackName) {
				$found = $stack;
				break;
			}
		}
		Assert::assertNotNull($found, 'Stack "' . $stackName . '" not found');
		$cardCount = count($found['cards'] ?? []);
		Assert::assertEquals((int)$count, $cardCount, 'Expected ' . $count . ' cards in stack "' . $stackName . '", got ' . $cardCount);
	}

	/**
	 * @Then /^the board should have labels "([^"]*)"$/
	 */
	public function theBoardShouldHaveLabels($labelList) {
		$expectedLabels = array_map('trim', explode(',', $labelList));
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$board = json_decode((string)$this->getResponse()->getBody(), true);
		$actualLabels = array_map(fn ($l) => $l['title'], $board['labels'] ?? []);
		foreach ($expectedLabels as $expected) {
			Assert::assertContains($expected, $actualLabels, 'Label "' . $expected . '" not found on board. Existing: ' . implode(', ', $actualLabels));
		}
	}

	/**
	 * @Then /^the card "([^"]*)" should have duedate "([^"]*)"$/
	 */
	public function theCardShouldHaveDuedate($cardTitle, $expectedDuedate) {
		$card = $this->findCardOnBoard($cardTitle);
		Assert::assertNotNull($card, 'Card "' . $cardTitle . '" not found on board');
		Assert::assertNotEmpty($card['duedate'], 'Card "' . $cardTitle . '" has no duedate set');
		Assert::assertEquals($expectedDuedate, $card['duedate'], 'Duedate mismatch for card "' . $cardTitle . '"');
	}

	/**
	 * @Then /^the card "([^"]*)" should not have a duedate$/
	 */
	public function theCardShouldNotHaveADuedate($cardTitle) {
		$card = $this->findCardOnBoard($cardTitle);
		Assert::assertNotNull($card, 'Card "' . $cardTitle . '" not found on board');
		Assert::assertEmpty($card['duedate'], 'Expected no duedate for card "' . $cardTitle . '", got "' . ($card['duedate'] ?? '') . '"');
	}

	/**
	 * @Then /^the card "([^"]*)" should have description "([^"]*)"$/
	 */
	public function theCardShouldHaveDescription($cardTitle, $expectedDescription) {
		$card = $this->findCardOnBoard($cardTitle);
		Assert::assertNotNull($card, 'Card "' . $cardTitle . '" not found on board');
		Assert::assertEquals($expectedDescription, $card['description'], 'Card description mismatch');
	}

	private function findCardOnBoard(string $cardTitle): ?array {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/stacks/' . $this->board['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$stacks = json_decode((string)$this->getResponse()->getBody(), true);
		foreach ($stacks as $stack) {
			foreach ($stack['cards'] ?? [] as $card) {
				if ($card['title'] === $cardTitle) {
					return $card;
				}
			}
		}
		return null;
	}

}
