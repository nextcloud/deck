<?php

/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


use Behat\Behat\Hook\Scope\BeforeScenarioScope;

require_once __DIR__ . '/../../vendor/autoload.php';


trait RequestTrait {

	/** @var RequestContext */
	protected $requestContext;

	/** @BeforeScenario */
	public function gatherRequestTraitContext(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();
		$this->requestContext = $environment->getContext('RequestContext');
	}

	public function getResponse() {
		return $this->requestContext->getResponse();
	}
}
