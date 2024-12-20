<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use DateTime;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;

/**
 * @method string getTitle()
 * @method string getDescription()
 * @method string getDescriptionPrev()
 * @method int getStackId()
 * @method int getOrder()
 * @method int getLastModified()
 * @method int getCreatedAt()
 * @method bool getArchived()
 * @method int getDeletedAt()
 * @method void setDeletedAt(int $deletedAt)
 * @method bool getNotified()
 * @method ?DateTime getDone()
 * @method void setDone(?DateTime $done)
 *
 * @method void setLabels(Label[] $labels)
 * @method null|Label[] getLabels()
 *
 * @method void setAssignedUsers(Assignment[] $users)
 * @method null|User[] getAssignedUsers()
 *
 * @method void setAttachments(Attachment[] $attachments)
 * @method null|Attachment[] getAttachments()
 *
 * @method void setAttachmentCount(int $count)
 * @method null|int getAttachmentCount()
 *
 * @method void setCommentsUnread(int $count)
 * @method null|int getCommentsUnread()
 *
 * @method void setCommentsCount(int $count)
 * @method null|int getCommentsCount()
 *
 * @method void setOwner(string $user)
 * @method null|string getOwner()
 *
 * @method void setRelatedStack(Stack $stack)
 * @method null|Stack getRelatedStack()
 *
 * @method void setRelatedBoard(Board $board)
 * @method null|Board getRelatedBoard()
 */
class Card extends RelationalEntity {
	public const TITLE_MAX_LENGTH = 255;

	protected string $title = '';
	protected $description;
	protected $descriptionPrev;
	protected $stackId;
	protected $type;
	protected $lastModified;
	protected $lastEditor;
	protected $createdAt;
	protected $labels;
	protected $assignedUsers;
	protected $attachments;
	protected $attachmentCount;
	protected $owner;
	protected $order;
	protected $archived = false;
	protected $done = null;
	protected $duedate;
	protected $notified = false;
	protected $deletedAt = 0;
	protected $commentsUnread = 0;
	protected $commentsCount = 0;

	protected $relatedStack = null;
	protected $relatedBoard = null;

	private $databaseType = 'sqlite';

	public const DUEDATE_FUTURE = 0;
	public const DUEDATE_NEXT = 1;
	public const DUEDATE_NOW = 2;
	public const DUEDATE_OVERDUE = 3;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('stackId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('done', 'datetime');
		$this->addType('notified', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('duedate', 'datetime');
		$this->addRelation('labels');
		$this->addRelation('assignedUsers');
		$this->addRelation('attachments');
		$this->addRelation('attachmentCount');
		$this->addRelation('participants');
		$this->addRelation('commentsUnread');
		$this->addRelation('commentsCount');
		$this->addResolvable('owner');

		$this->addRelation('relatedStack');
		$this->addRelation('relatedBoard');
	}

	public function setDatabaseType($type) {
		$this->databaseType = $type;
	}

	public function getCalendarObject(): VCalendar {
		$calendar = new VCalendar();
		$event = $calendar->createComponent('VTODO');
		$event->UID = 'deck-card-' . $this->getId();
		if ($this->getDuedate()) {
			$creationDate = new DateTime();
			$creationDate->setTimestamp($this->createdAt);
			$event->DTSTAMP = $creationDate;
			$event->DUE = new DateTime($this->getDuedate()->format('c'), new DateTimeZone('UTC'));
		}
		$event->add('RELATED-TO', 'deck-stack-' . $this->getStackId());

		// FIXME: For write support: CANCELLED / IN-PROCESS handling
		if ($this->getDone() || $this->getArchived()) {
			$date = new DateTime();
			$date->setTimestamp($this->getLastModified());
			$event->STATUS = 'COMPLETED';
			$event->COMPLETED = $this->getDone() ? $this->getDone() : $this->getArchived();
		} else {
			$event->STATUS = 'NEEDS-ACTION';
		}

		// $event->add('PERCENT-COMPLETE', 100);

		$labels = $this->getLabels() ?? [];
		$event->CATEGORIES = array_map(function ($label): string {
			return $label->getTitle();
		}, $labels);

		$event->SUMMARY = $this->getTitle();
		$event->DESCRIPTION = $this->getDescription();
		$calendar->add($event);
		return $calendar;
	}

	public function getDaysUntilDue(): ?int {
		if ($this->getDuedate() === null) {
			return null;
		}

		$today = new DateTime();
		$today->setTime(0, 0);

		$matchDate = DateTime::createFromInterface($this->getDuedate());
		$matchDate->setTime(0, 0);

		$diff = $today->diff($matchDate);
		return (int)$diff->format('%R%a'); // Extract days count in interval
	}

	public function getCalendarPrefix(): string {
		return 'card';
	}

	public function getETag(): string {
		return md5((string)$this->getLastModified());
	}
}
