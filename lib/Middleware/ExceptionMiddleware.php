<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Middleware;

use OCA\Deck\Controller\PageController;
use OCA\Deck\StatusException;
use OCA\Deck\Exceptions\ConflictException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Util;
use OCP\IConfig;

class ExceptionMiddleware extends Middleware {

	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;
	/** @var IRequest */
	private $request;

	/**
	 * SharingMiddleware constructor.
	 *
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(ILogger $logger, IConfig $config, IRequest $request) {
		$this->logger = $logger;
		$this->config = $config;
		$this->request = $request;
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
			if ($this->config->getSystemValue('loglevel', Util::WARN) === Util::DEBUG) {
				$this->logger->logException($exception);
			}

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

		if (strpos(get_class($controller), 'OCA\\Deck\\Controller\\') === 0) {
			$response = [
				'status' => 500,
				'message' => $exceptionMessage,
				'requestId' => $this->request->getId(),
			];
			$this->logger->logException($exception);
			if ($debugMode === true) {
				$response['exception'] = (array) $exception;
			}
			return new JSONResponse($response, 500);
		}

		throw $exception;
	}
}
