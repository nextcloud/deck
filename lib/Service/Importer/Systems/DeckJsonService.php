<?php
/**
 * @copyright Copyright (c) 2023 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service\Importer\Systems;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Service\Importer\ABoardImportService;
use OCP\IUser;
use OCP\IUserManager;

class DeckJsonService extends ABoardImportService {
	public static $name = 'Deck JSON';
	/** @var IUser[] */
	private array $members = [];
	private array $tmpCards = [];

	public function __construct(
		private IUserManager $userManager,
	) {
	}

	public function bootstrap(): void {
		$this->validateUsers();
	}

	public function getJsonSchemaPath(): string {
		return implode(DIRECTORY_SEPARATOR, [
			__DIR__,
			'..',
			'fixtures',
			'config-deckJson-schema.json',
		]);
	}

	public function validateUsers(): void {
		if (empty($this->getImportService()->getConfig('uidRelation')) || !isset($this->getImportService()->getData()->members)) {
			return;
		}
		foreach ($this->getImportService()->getConfig('uidRelation') as $exportUid => $nextcloudUid) {
			$user = array_filter($this->getImportService()->getData()->members, function (\stdClass $u) use ($exportUid) {
				return $u->username === $exportUid;
			});
			if (!$user) {
				throw new \LogicException('Trello user ' . $exportUid . ' not found in property "members" of json data');
			}
			if (!is_string($nextcloudUid) && !is_numeric($nextcloudUid)) {
				throw new \LogicException('User on setting uidRelation is invalid');
			}
			$nextcloudUid = (string) $nextcloudUid;
			$this->getImportService()->getConfig('uidRelation')->$exportUid = $this->userManager->get($nextcloudUid);
			if (!$this->getImportService()->getConfig('uidRelation')->$exportUid) {
				throw new \LogicException('User on setting uidRelation not found: ' . $nextcloudUid);
			}
			$user = current($user);
			$this->members[$user->id] = $this->getImportService()->getConfig('uidRelation')->$exportUid;
		}
	}

	public function mapMember($uid): ?string {

		$uidCandidate = $this->members[$uid]?->getUID() ?? null;
		if ($uidCandidate) {
			return $uidCandidate;
		}

		if ($this->userManager->userExists($uid)) {
			return $uid;
		}

		return null;
	}

	public function getCardAssignments(): array {
		$assignments = [];
		foreach ($this->tmpCards as $sourceCard) {
			foreach ($sourceCard->assignedUsers as $idMember) {
				$assignment = new Assignment();
				$assignment->setCardId($this->cards[$sourceCard->id]->getId());
				$assignment->setParticipant($idMember->participant->uid);
				$assignment->setType($idMember->participant->type);
				$assignments[$sourceCard->id][] = $assignment;
			}
		}
		return $assignments;
	}

	public function getComments(): array {
		// Comments are not implemented in export
		return [];
	}

	public function getCardLabelAssignment(): array {
		$cardsLabels = [];
		foreach ($this->tmpCards as $sourceCard) {
			foreach ($sourceCard->labels as $label) {
				$cardId = $this->cards[$sourceCard->id]->getId();
				$labelId = $this->labels[$label->id]->getId();
				$cardsLabels[$cardId][] = $labelId;
			}
		}
		return $cardsLabels;
	}

	public function getBoard(): Board {
		$board = $this->getImportService()->getBoard();
		if (empty($this->getImportService()->getData()->title)) {
			throw new BadRequestException('Invalid name of board');
		}
		$board->setTitle($this->getImportService()->getData()->title);
		$board->setOwner($this->getImportService()->getData()->owner->uid);
		$board->setColor($this->getImportService()->getData()->color);
		return $board;
	}

	/**
	 * @return Label[]
	 */
	public function getLabels(): array {
		foreach ($this->getImportService()->getData()->labels as $label) {
			$newLabel = new Label();
			$newLabel->setTitle($label->title);
			$newLabel->setColor($label->color);
			$newLabel->setBoardId($this->getImportService()->getBoard()->getId());
			$this->labels[$label->id] = $newLabel;
		}
		return $this->labels;
	}

	/**
	 * @return Stack[]
	 */
	public function getStacks(): array {
		$return = [];
		foreach ($this->getImportService()->getData()->stacks as $index => $source) {
			if ($source->title) {
				$stack = new Stack();
				$stack->setTitle($source->title);
				$stack->setBoardId($this->getImportService()->getBoard()->getId());
				$stack->setOrder($source->order);
				$return[$source->id] = $stack;
			}

			if (isset($source->cards)) {
				foreach ($source->cards as $card) {
					$card->stackId = $index;
					$this->tmpCards[] = $card;
				}
				// TODO: check older exports as currently there is a bug that adds lists to it with different index
			}
		}
		return $return;
	}

	/**
	 * @return Card[]
	 */
	public function getCards(): array {
		$cards = [];
		foreach ($this->tmpCards as $cardSource) {
			$card = new Card();
			$card->setTitle($cardSource->title);
			$card->setLastModified($cardSource->lastModified);
			$card->setCreatedAt($cardSource->createdAt);
			$card->setArchived($cardSource->archived);
			$card->setDescription($cardSource->description);
			$card->setStackId($this->stacks[$cardSource->stackId]->getId());
			$card->setType('plain');
			$card->setOrder($cardSource->order);
			$card->setOwner($this->getBoard()->getOwner());
			$card->setDuedate($cardSource->duedate);
			$cards[$cardSource->id] = $card;
		}
		return $cards;
	}

	/**
	 * @return Acl[]
	 */
	public function getAclList(): array {
		// FIXME: To implement
		$return = [];
		foreach ($this->members as $member) {
			if ($member->getUID() === $this->getImportService()->getConfig('owner')->getUID()) {
				continue;
			}
			$acl = new Acl();
			$acl->setBoardId($this->getImportService()->getBoard()->getId());
			$acl->setType(Acl::PERMISSION_TYPE_USER);
			$acl->setParticipant($member->getUID());
			$acl->setPermissionEdit(false);
			$acl->setPermissionShare(false);
			$acl->setPermissionManage(false);
			// FIXME: Figure out a way to collect and aggregate warnings about users
			// FIXME: Maybe have a dry run?
			$return[] = $acl;
		}
		return $return;
	}

	private function replaceUsernames(string $text): string {
		foreach ($this->getImportService()->getConfig('uidRelation') as $trello => $nextcloud) {
			$text = str_replace($trello, $nextcloud->getUID(), $text);
		}
		return $text;
	}

	public function getBoards(): array {
		// Old format has just the raw board data, new one a key boards
		$data = $this->getImportService()->getData();
		return array_values((array)($data->boards ?? $data));
	}

	public function reset(): void {
		parent::reset();
		$this->tmpCards = [];
	}
}
