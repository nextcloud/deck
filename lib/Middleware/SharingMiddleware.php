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

use OCA\Deck\StatusException;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\Util;
use OCP\IConfig;


class SharingMiddleware extends Middleware {

	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;

	/**
	 * SharingMiddleware constructor.
	 *
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(ILogger $logger, IConfig $config) {
		$this->logger = $logger;
		$this->config = $config;
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
		if ($exception instanceof StatusException) {
			if($this->config->getSystemValue('loglevel', Util::WARN) === Util::DEBUG) {
				$this->logger->logException($exception);
			}
			return new JSONResponse([
				'status' => $exception->getStatus(),
				'message' => $exception->getMessage()
			], $exception->getStatus());
		}
		throw $exception;
	}

}