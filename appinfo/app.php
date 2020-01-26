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

use OCA\Deck\AppInfo\Application;
use OCP\AppFramework\QueryException;

if ((@include_once __DIR__ . '/../vendor/autoload.php')=== false) {
	throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

try {
	/** @var Application $app */
	$app = \OC::$server->query(Application::class);
	$app->register();
} catch (QueryException $e) {
}

/** Load activity style global so it is availabile in the activity app as well */
\OC_Util::addStyle('deck', 'activity');
