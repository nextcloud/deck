<?php

namespace OCA\Deck\Federation;

use OCP\Http\Client\IClientService;
use OC\Http\Client\Response;
use OCP\Http\Client\IResponse;
use OCP\AppFramework\Http;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ServerException;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class DeckFederationProxy {
	public function __construct(
		private IClientService $clientService,
		private IuserSession $userSession,
		private IConfig $config,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
	){}
	protected function generateDefaultRequestOptions(
		?string $cloudId,
		#[SensitiveParameter]
		?string $accessToken,
	): array {
		   $options = [
				'verify' => !$this->config->getSystemValueBool('sharing.federation.allowSelfSignedCertificates'),
				'nextcloud' => [
					'allow_local_address' => $this->config->getSystemValueBool('allow_local_remote_servers'),
				],
				'headers' => [
					'Cookie' => 'XDEBUG_SESSION=PHPSTORM',
					'Accept' => 'application/json',
					'x-nextcloud-federation' => 'true',
					'OCS-APIRequest' => 'true',
					'Accept-Language' => $this->l10nFactory->getUserLanguage($this->userSession->getUser()),
					'deck-federation-accesstoken' => $accessToken,
				],
				'timeout' => 5,
		   ];

		if ($cloudId !== null && $accessToken !== null) {
		}

		return $options;
	}

	protected function prependProtocolIfNotAvailable(string $url): string {
		if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
			$url = 'https://' . $url;
		}
		return $url;
	}

	/**
	 * @param 'get'|'post'|'put'|'delete' $verb
	 * @throws \Exception
	 */
	protected function request(
		string $verb,
		?string $cloudId,
		#[SensitiveParameter]
		?string $accessToken,
		string $url,
		array $parameters,
	): IResponse {
		$requestOptions = $this->generateDefaultRequestOptions($cloudId, $accessToken);
		if (!empty($parameters)) {
			$requestOptions['json'] = $parameters;
		}

		try {
			return $this->clientService->newClient()->{$verb}(
				$this->prependProtocolIfNotAvailable($url),
				$requestOptions
			);
		} catch (ClientException $e) {
			$status = $e->getResponse()->getStatusCode();

			   try {
				   $body = $e->getResponse()->getBody()->getContents();
				   $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
				   $e->getResponse()->getBody()->rewind();
				   if (!is_array($data)) {
					   throw new \RuntimeException('JSON response is not an array');
				   }
			   } catch (\Throwable $e) {
				   throw new \Exception('Error parsing JSON response', $e->getCode(), $e);
			   }

			   $clientException = new \Exception($e->getMessage(), $status, $e);
			   $this->logger->debug('Client error from remote', ['exception' => $clientException]);
			   return new Response($e->getResponse(), false);
		   } catch (ServerException|\Throwable $e) {
			   $serverException = new \Exception($e->getMessage(), $e->getCode(), $e);
			   $this->logger->error('Could not reach remote', ['exception' => $serverException]);
			   throw $serverException;
		}
	}

	public function get(string $cloudId, string $shareToken, string $url, array $params = []):IResponse {
		return $this->request("get", $cloudId, $shareToken, $url, $params);
	}
	public function post(string $cloudId, string $shareToken, string $url, array $params = []):IResponse {
		return $this->request("post", $cloudId, $shareToken, $url, $params);
	}
	public function delete(string $cloudId, string $shareToken, string $url, array $params = []):IResponse {
		return $this->request("delete", $cloudId, $shareToken, $url, $params);
	}
	public function getOCSData(IResponse $response, array $allowedStatusCodes = [Http::STATUS_OK]): array {
		if (!in_array($response->getStatusCode(), $allowedStatusCodes, true)) {
			$this->logUnexpectedStatusCode(__METHOD__, $response->getStatusCode());
		}

		try {
			$content = $response->getBody();
			$responseData = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($responseData)) {
				throw new \RuntimeException('JSON response is not an array');
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error parsing JSON response: ' . ($content ?? 'no-data'), ['exception' => $e]);
			throw new \Exception('Error parsing JSON response', $e->getCode(), $e);
		}

		return $responseData['ocs']['data'] ?? [];
	}

	/**
	 * @return Http::STATUS_BAD_REQUEST
	 */
	public function logUnexpectedStatusCode(string $method, int $statusCode, string $logDetails = ''): int {
		if ($this->config->getSystemValueBool('debug')) {
			$this->logger->error('Unexpected status code ' . $statusCode . ' returned for ' . $method . ($logDetails !== '' ? "\n" . $logDetails : ''));
		} else {
			$this->logger->debug('Unexpected status code ' . $statusCode . ' returned for ' . $method . ($logDetails !== '' ? "\n" . $logDetails : ''));
		}
		return Http::STATUS_BAD_REQUEST;
	}
}
