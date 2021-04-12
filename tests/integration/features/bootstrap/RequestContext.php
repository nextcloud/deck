<?php

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;
use Behat\Behat\Context\Context;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../../vendor/autoload.php';

class RequestContext implements Context {
	private $response;

	/** @var ServerContext */
	private $serverContext;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
	}

	private function getBaseUrl() {
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

	public function sendJSONrequest($method, $url, $data = []) {
		$client = new Client;
		try {
			$this->response = $client->request(
				$method,
				rtrim($this->serverContext->getBaseUrl(), '/') . '/' . ltrim($url, '/'),
				[
					'cookies' => $this->serverContext->getCookieJar(),
					'json' => $data,
					'headers' => [
						'requesttoken' => $this->serverContext->getReqestToken(),
					]
				]
			);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	public function sendOCSRequest($method, $url, $data = []) {
		$client = new Client;
		try {
			$this->response = $client->request(
				$method,
				rtrim($this->serverContext->getBaseUrl(), '/') . '/ocs/v2.php/' . ltrim($url, '/'),
				[
					'cookies' => $this->serverContext->getCookieJar(),
					'json' => $data,
					'headers' => [
						'requesttoken' => $this->serverContext->getReqestToken(),
						'OCS-APIREQUEST' => 'true',
						'Accept' => 'application/json'
					]
				]
			);
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	public function getResponse(): ResponseInterface {
		return $this->response;
	}
}
