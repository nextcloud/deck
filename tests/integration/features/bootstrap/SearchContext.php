<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class SearchContext implements Context {
	use RequestTrait;

	/** @var BoardContext */
	protected $boardContext;

	private $searchResults;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->boardContext = $environment->getContext('BoardContext');
	}
	
	/**
	 * @When /^searching for "([^"]*)"$/
	 * @param string $term
	 */
	public function searchingFor(string $term) {
		$this->requestContext->sendOCSRequest('GET', '/apps/deck/api/v1.0/search?term=' . urlencode($term), []);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$data = (string)$this->getResponse()->getBody();
		$this->searchResults = json_decode($data, true);
	}

	/**
	 * @When /^searching for '([^']*)'$/
	 * @param string $term
	 */
	public function searchingForQuotes(string $term) {
		$this->searchingFor($term);
	}

	/**
	 * @Then /^the board "([^"]*)" is found$/
	 */
	public function theBoardIsFound($arg1) {
		$ocsData = $this->searchResults['ocs']['data'];
		$found = false;
		foreach ($ocsData as $result) {
			if ($result['title'] === $arg1) {
				$found = true;
			}
		}
		Assert::assertTrue($found, 'Board can be found');
	}

	private function cardIsFound($arg1) {
		$ocsData = $this->searchResults['ocs']['data'];
		$found = false;
		foreach ($ocsData as $result) {
			if ($result['title'] === $arg1) {
				$found = true;
			}
		}
		return $found;
	}

	/**
	 * @Then /^the card "([^"]*)" is found$/
	 */
	public function theCardIsFound($arg1) {
		Assert::assertTrue($this->cardIsFound($arg1), 'Card can be found');
	}

	/**
	 * @Then /^the card "([^"]*)" is not found$/
	 */
	public function theCardIsNotFound($arg1) {
		Assert::assertFalse($this->cardIsFound($arg1), 'Card can not be found');
	}
}
