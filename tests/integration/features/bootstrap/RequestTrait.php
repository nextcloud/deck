<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

trait RequestTrait {

	private $baseUrl;
	private $adminUser;
	private $regularUser;
	private $cookieJar;

	private $response;

	public function __construct($baseUrl, $admin = 'admin', $regular_user_password = '123456') {
		$this->baseUrl = $baseUrl;
		$this->adminUser = $admin;
		$this->regularUser = $regular_user_password;
	}

	/** @var ServerContext */
	private $serverContext;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope)
	{
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
	}

	/**
	 * @Then the response should have a status code :code
	 * @param string $code
	 * @throws InvalidArgumentException
	 */
	public function theResponseShouldHaveStatusCode($code) {
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
	 * @Then /^the response Content-Type should be "([^"]*)"$/
	 * @param string $contentType
	 */
	public function theResponseContentTypeShouldbe($contentType) {
		Assert::assertEquals($contentType, $this->response->getHeader('Content-Type')[0]);
	}

	/**
	 * @Then the response should be a JSON array with the following mandatory values
	 * @param TableNode $table
	 * @throws InvalidArgumentException
	 */
	public function theResponseShouldBeAJsonArrayWithTheFollowingMandatoryValues(TableNode $table) {
		$this->response->getBody()->seek(0);
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
		$this->response->getBody()->seek(0);
		$realResponseArray = json_decode($this->response->getBody()->getContents(), true);
		if ((int)count($realResponseArray) !== (int)$length) {
			throw new InvalidArgumentException(
				sprintf(
					'Expected %d as length got %d',
					$length,
					count($realResponseArray)
				)
			);
		}
	}

	private function sendJSONrequest($method, $url, $data = []) {

		$client = new Client;
		try {
			$this->response = $client->request(
				$method,
				$this->baseUrl . $url,
				[
					'cookies' => $this->serverContext->getCookieJar(),
					'json' => $data,
					'headers' => [
						'requesttoken' => $this->serverContext->getReqestToken()
					]
				]
			);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}
}
