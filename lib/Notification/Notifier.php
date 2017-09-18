<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Notification;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\RichObjectStrings\Definitions;

class Notifier implements INotifier {
	/** @var IFactory */
	protected $l10nFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var IUserManager */
	protected $userManager;
	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $url,
		IUserManager $userManager,
		CardMapper $cardMapper,
		BoardMapper $boardMapper,
		Definitions $definitions
	) {
		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$definitions->addDefinition('highlight', [
			'author' => 'Deck',
			'app' => 'deck',
			'since' => '12.0.0',
			'parameters' => [
				'name' => [
					'since' => '12.0.0',
					'required' => true,
					'description' => 'The text that should be highlighted.',
					'example' => 'Foobar',
				]
			],
		]);
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, $languageCode) {
		if($notification->getApp() !== 'deck') {
			throw new \InvalidArgumentException();
		}
		$l = $this->l10nFactory->get('deck', $languageCode);
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('deck', 'deck-dark.svg')));

		switch($notification->getSubject()) {
			case 'board-shared':
				$boardId = $notification->getObjectId();
				$params = $notification->getSubjectParameters();
				$initiator = \OC::$server->getUserManager()->get($params[1]);
				if($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[1];
				}
				$notification->setParsedSubject(
					(string) $l->t('The board "%s" has been shared with you by %s.', [$params[0], $dn])
				)->setRichSubject(
					(string) $l->t('{user} has shared the board {board} with you.'),
					[
						'user' => [
							'type' => 'user',
							'id' => $params[1],
							'name' => $dn,
						],
						'board' => [
							'type' => 'highlight',
							'name' => $params[0],
						],
					]
				);
				$notification->setLink($this->url->linkToRouteAbsolute('deck.page.index') . '#!/board/'.$boardId.'/');
				break;
		}
		return $notification;
	}
}