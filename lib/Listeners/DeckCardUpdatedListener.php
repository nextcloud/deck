<?php

declare(strict_types=1);

namespace OCA\Deck\Listeners;

use DateInterval;
use DateTime;
use OC\Log;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Notification\Subjects\CardStackUpdated;
use OCA\Deck\Queue\Tasks\DueDateAlarmTask;
use OCA\Deck\Service\DeckCommentActivityService;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\BoardService;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Notification\IManager;

class DeckCardUpdatedListener implements IEventListener
{
    public function __construct(
        private readonly Log $logger,
        private readonly IManager $manager,
        private readonly IUserSession $userSession,
        private readonly CardService $cardService,
        private readonly StackService $stackService,
        private readonly BoardService $boardService,
        private readonly DeckCommentActivityService $deckCommentActivityService,
        private readonly IJobList $jobList,
    ) {
    }

    public function handle(Event $event): void
    {
        if ( !$event instanceof CardUpdatedEvent ) {
            $this->logger->info('Event caught: DeckCardUpdatedListener -- But not correct instance?');
            return;
        }

        [$currentCard, $previousCard] = $this->cardService->enrichCards([
            $event->getCard(),
            $event->getCardBefore(),
        ]);

        $this->logger->info('Event caught: DeckCardUpdatedListener', [
            'event' => ['card_id' => $currentCard->getId(), 'stack_id' => $currentCard->getStackId()],
            'previous' => ['card_id' => $previousCard->getId(), 'stack_id' => $previousCard->getStackId()],
        ]);

        $currentAssignedUsers = array_map(fn(Assignment $assignment) => $assignment->getParticipant(), $currentCard->getAssignedUsers());
        $previousAssignedUsers = array_map(fn(Assignment $assignment) => $assignment->getParticipant(), $previousCard->getAssignedUsers());

        $subjectStack = $this->stackService->find($currentCard->getStackId());
        $previousStack = $this->stackService->find($previousCard->getStackId());

        $subjectBoard = $this->boardService->find($subjectStack->getBoardId());
        $previousBoard = $this->boardService->find($previousStack->getBoardId());


        // DueDate is altered
        if ($currentCard->getDuedate() && ($currentCard->getDuedate() !== $previousCard->getDuedate()) ) {
            $executeAfter = DateTime::createFromInterface($currentCard->getDuedate())
                ->sub(DateInterval::createFromDateString('15 minutes'))
                ->getTimestamp();

            $this->jobList->scheduleAfter(DueDateAlarmTask::class, $executeAfter, [
                'card_id' => $currentCard->getId(),
            ]);
        }

        if (($currentCard->getStackId() !== $previousCard->getStackId())) {
            // We always want to comment when card changes stack
            $this->deckCommentActivityService->cardChangesStack($currentCard, $previousStack->getTitle(), $subjectStack->getTitle());

            // We only want to send notifications to current and previous assigned user
            if ($currentAssignedUsers !== [] && $previousAssignedUsers !== []) {
                $notification = $this->manager->createNotification();
                $notification->setApp(Application::APP_ID)
                    ->setDateTime(new DateTime())
                    ->setObject('card', (string)$currentCard->getId())
                    ->setSubject(CardStackUpdated::SUBJECT, [
                        'card' => $currentCard,
                        'stack' => $subjectStack,
                        'board' => $subjectBoard,
                        'actor' => ['id' => $this->userSession->getUser()->getUID(), 'display_name' => $this->userSession->getUser()->getDisplayName()]])
                    ->setMessage(CardStackUpdated::SUBJECT, [
                        'stack' => $previousStack,
                        'board' => $previousBoard
                    ]);

                /* @var string $user */
                foreach ($currentAssignedUsers as $user) {
                    if ($user === $this->userSession->getUser()->getUID()) {
                        $this->logger->info('Assigned user same as logged in user -- skipping', ['user' => $this->userSession->getUser()->getUID()]);
                        continue;
                    }

                    $notification->setUser($user);
                    $this->manager->notify($notification);
                }
            }
        }

        $this->logger->info('Event ended: DeckCardUpdatedListener');
    }
}
