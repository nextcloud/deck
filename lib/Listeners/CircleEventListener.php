<?php

namespace OCA\Deck\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Event\AclDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;

class CircleEventListener implements IEventListener {

	/** @var AclMapper */
	private $aclMapper;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(AclMapper $aclMapper, IEventDispatcher $eventDispatcher) {
		$this->aclMapper = $aclMapper;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function handle(Event $event): void {
		if ($event instanceof CircleDestroyedEvent) {
			$circleId = $event->getCircle()->getSingleId();
			$acls = $this->aclMapper->findByParticipant(Acl::PERMISSION_TYPE_CIRCLE, $circleId);
			foreach ($acls as $acl) {
				$this->aclMapper->delete($acl);
				$this->eventDispatcher->dispatchTyped(new AclDeletedEvent($acl));
			}
		}
	}
}
