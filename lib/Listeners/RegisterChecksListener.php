<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Listeners;

use OCA\FlowWebhooks\AppInfo\Application;
use OCA\FlowWebhooks\Flow\ParameterCheck;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;

class RegisterChecksListener implements IEventListener {

	/** @var ParameterCheck */
	private $parameterCheck;

	public function __construct(ParameterCheck $parameterCheck) {
		$this->parameterCheck = $parameterCheck;
	}

	public function handle(Event $event): void {
		if (!($event instanceof RegisterChecksEvent)) {
			return;
		}
		$event->registerCheck($this->parameterCheck);

		Util::addScript(Application::APP_ID, Application::APP_ID);
	}
}
