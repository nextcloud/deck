<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Middleware;

use OCA\Deck\Controller\PageController;
use OCA\Deck\Exceptions\ConflictException;
use OCA\Deck\StatusException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ExceptionMiddleware extends Middleware {

	/**
	 * SharingMiddleware constructor.
	 */
	public function __construct(
		private LoggerInterface $logger,
		private IConfig $config,
		private IRequest $request,
	) {
	}

	/**
	 * Return JSON error response if the user has no sufficient permission
	 *
	 * @param \OCP\AppFramework\Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return JSONResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if (get_class($controller) === PageController::class) {
			throw $exception;
		}

		$debugMode = $this->config->getSystemValue('debug', false);
		$exceptionMessage = $debugMode !== true
			? 'Internal server error: Please contact the server administrator if this error reappears multiple times, please include the request ID "' . $this->request->getId() . '" below in your report.'
			: $exception->getMessage();

		// uncatched DoesNotExistExceptions will be thrown when the main entity is not found
		// we return a 403 so we don't leak information over existing entries
		// TODO: At some point those should properly be catched in the service classes
		if ($exception instanceof DoesNotExistException) {
			return new JSONResponse([
				'status' => 403,
				'message' => 'Permission denied'
			], 403);
		}

		if ($exception instanceof StatusException) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			if ($exception instanceof ConflictException) {
				return new JSONResponse([
					'status' => $exception->getStatus(),
					'message' => $exception->getMessage(),
					'data' => $exception->getData(),
				], $exception->getStatus());
			}

			if ($controller instanceof OCSController) {
				$exception = new OCSException($exception->getMessage(), $exception->getStatus(), $exception);
				throw $exception;
			}

			return new JSONResponse([
				'status' => $exception->getStatus(),
				'message' => $exception->getMessage(),
			], $exception->getStatus());
		}

		if (str_starts_with(get_class($controller), 'OCA\\Deck\\Controller\\')) {
			$response = [
				'status' => 500,
				'message' => $exceptionMessage,
				'requestId' => $this->request->getId(),
			];
			$this->logger->error($exception->getMessage(), ['exception' => $exception]);
			if ($debugMode === true) {
				$response['exception'] = (array)$exception;
			}
			return new JSONResponse($response, 500);
		}

		throw $exception;
	}
}
