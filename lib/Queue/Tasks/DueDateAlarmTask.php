<?php

declare(strict_types=1);

namespace OCA\Deck\Queue\Tasks;

use DateTime;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Notification\Subjects\DueDateAlarm;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Notification\IManager;

class DueDateAlarmTask extends QueuedJob
{
    private readonly IManager $manager;
    private readonly CardMapper $cardMapper;
    private readonly StackMapper $stackMapper;
    private readonly BoardMapper $boardMapper;
    private readonly AssignmentMapper $assignedUsersMapper;

    public function __construct(
        ITimeFactory $time,
        IManager $manager,
        CardMapper $cardMapper,
        StackMapper $stackMapper,
        BoardMapper $boardMapper,
        AssignmentMapper $assignedUsersMapper,
    ) {
        parent::__construct($time);

        $this->manager = $manager;
        $this->cardMapper = $cardMapper;
        $this->stackMapper = $stackMapper;
        $this->boardMapper = $boardMapper;
        $this->assignedUsersMapper = $assignedUsersMapper;
    }

    protected function run($argument): void
    {
        // Gather all data necessary
        $card          = $this->cardMapper->find($argument['card_id']);
        $stack         = $this->stackMapper->find($card->getStackId());
        $board         = $this->boardMapper->find($stack->getBoardId());
        $assignedUsers = array_map(fn(Assignment $assignment) => $assignment->getParticipant(),
            array_values(array_filter($this->assignedUsersMapper->findIn([$card->getId()]),
                function (Assignment $assignment) use ($card) {
                    return $assignment->getCardId() === $card->getId();
                }))
        );

        // Create notification
        $notification = $this->manager->createNotification();
        $notification->setApp(Application::APP_ID)
            ->setDateTime(new DateTime())
            ->setObject('card', (string)$card->getId())
            ->setSubject(DueDateAlarm::SUBJECT, [
                'card'  => $card,
                'stack' => $stack,
                'board' => $board,
            ])
            ->setMessage(DueDateAlarm::SUBJECT, []);

        // Notify users
        foreach ($assignedUsers as $user) {
            $notification->setUser($user);
            $this->manager->notify($notification);
        }
    }
}
