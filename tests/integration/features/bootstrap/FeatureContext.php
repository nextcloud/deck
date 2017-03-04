<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Exception\ClientException;

require_once __DIR__ . '/../../vendor/autoload.php';

class FeatureContext implements Context {

	use WebDav;

	/** @var string */
	private $mappedUserId;

	private $lastInsertIds = array();

	/**
	 * @When :user requests the deck list
	 */
	/**
	 * @When Sending a :method to :url with JSON
	 */
	public function sendingAToWithJSON($method, $url, \Behat\Gherkin\Node\PyStringNode $data) {
		$baseUrl = substr($this->baseUrl, 0, -5);

		$client = new Client;
		$request = $client->createRequest(
			$method,
			$baseUrl . $url,
			[
				'cookies' => $this->cookieJar,
				'json' => json_decode($data)
			]
		);
		$request->addHeader('requesttoken', $this->requestToken);
		try {
			$this->response = $client->send($request);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}


	/**
	 * @When :user creates a new deck with name :name
	 */
	public function createsANewDeckWithName($user, $content) {
		$client = new GuzzleHttp\Client();
		$this->response = $client->post(
			'http://localhost:8080/index.php/apps/deck/boards',
			[
				'form_params' => [
					'name' => $name,
				],
				'auth' => [
					$this->mappedUserId,
					'test',
				],
			]
		);
	}

	/**
	 * @Then the response should have a status code :code
	 * @param string $code
	 * @throws InvalidArgumentException
	 */
	public function theResponseShouldHaveAStatusCode($code) {
		$currentCode = $this->response->getStatusCode();
		if ($currentCode !== (int)$code) {
			throw new InvalidArgumentException(
				sprintf(
					'Expected %s as code got %s',
					$code,
					$currentCode
				)
			);
		}
	}

	/**
	 * @Then the response should be a JSON array with the following mandatory values
	 * @param TableNode $table
	 * @throws InvalidArgumentException
	 */
	public function theResponseShouldBeAJsonArrayWithTheFollowingMandatoryValues(TableNode $table) {
		$expectedValues = $table->getColumnsHash();
		$realResponseArray = json_decode($this->response->getBody()->getContents(), true);

		foreach ($expectedValues as $value) {
			if ((string)$realResponseArray[$value['key']] !== (string)$value['value']) {
				throw new InvalidArgumentException(
					sprintf(
						'Expected %s for key %s got %s',
						(string)$value['value'],
						$value['key'],
						(string)$realResponseArray[$value['key']]
					)
				);
			}
		}
	}

	/**
	 * @Then the response should be a JSON array with a length of :length
	 * @param int $length
	 * @throws InvalidArgumentException
	 */
	public function theResponseShouldBeAJsonArrayWithALengthOf($length) {
		$realResponseArray = json_decode($this->response->getBody()->getContents(), true);
    PHPUnit_Framework_Assert::assertEquals($realResponseArray, "foo");
		if((int)count($realResponseArray) !== (int)$length) {
			throw new InvalidArgumentException(
				sprintf(
					'Expected %d as length got %d',
					$length,
					count($realResponseArray)
				)
			);
		}
	}

  /**
     * @Then /^I should get:$/
     *
     * @param PyStringNode $string
     * @throws \Exception
     */
    public function iShouldGet(PyStringNode $string)
    {
        if ((string) $string !== trim($this->cliOutput)) {
            throw new Exception(sprintf(
                'Expected "%s" but received "%s".',
                $string,
                $this->cliOutput
            ));
        }

        return;
    }

    private function sendJSONrequest($method, $url, $data) {
		$baseUrl = substr($this->baseUrl, 0, -5);

		$client = new Client;
		$request = $client->createRequest(
			$method,
			$baseUrl . $url,
			[
				'cookies' => $this->cookieJar,
				'json' => $data
			]
		);
		$request->addHeader('requesttoken', $this->requestToken);
		try {
			$this->response = $client->send($request);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Given /^creates a board named "([^"]*)" with color "([^"]*)"$/
	 */
	public function createsABoardNamedWithColor($title, $color) {

		$this->sendJSONrequest('POST', '/index.php/apps/deck/boards', [
				'title' => $title,
				'color' => $color
			]
		);
		$response = json_decode($this->response->getBody()->getContents(), true);
		$this->lastInsertIds[$title] = $response['id'];
	}

	/**
	 * @When /^"([^"]*)" fetches the board named "([^"]*)"$/
	 */
	public function fetchesTheBoardNamed($user, $boardName) {
		$this->loggingInUsingWebAs($user);
		$id = $this->lastInsertIds[$boardName];
		$this->sendJSONrequest('GET', '/index.php/apps/deck/boards/'.$id, []);
	}

}
