<?php

declare(strict_types=1);

namespace OCA\Deck\Notification\Subjects;

use OC;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;

class CardStackUpdated
{
    public const SUBJECT = 'card_stack_updated';

    public function __construct(
        private readonly IURLGenerator $urlGenerator,
    ) {
        //
    }

    public function handle(INotification $notification, IL10N $l): INotification
    {
        $subjectCard = $notification->getSubjectParameters()['card'];
        $subjectStack = $notification->getSubjectParameters()['stack'];
        $subjectBoard = $notification->getSubjectParameters()['board'];
        $subjectActor = $notification->getSubjectParameters()['actor'];


        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-dark.svg')));
        $notification->setRichSubject($l->t('{card} {board} has been updated by {actor}'), [
            'actor' => [
                'type' => 'user',
                'id'   => $subjectActor['id'],
                'name' => $subjectActor['display_name'],
            ],
            'card'  => [
                'type'      => 'deck-card',
                'id'        => (string)$subjectCard['id'],
                'name'      => $subjectCard['title'],
                'boardname' => $subjectBoard['title'],
                'stackname' => $subjectStack['title'],
                'link'      => $this->getCardUrl($subjectBoard['id'], $subjectCard['id']),
            ],
            'board' => [
                'type' => 'deck-board',
                'id'   => (string)$subjectBoard['id'],
                'name' => "[{$subjectBoard['title']}]",
                'link' => $this->getBoardUrl($subjectBoard['id']),
            ]
        ]);

        $previousStack = $notification->getMessageParameters()['stack'];
        $previousBoard = $notification->getMessageParameters()['board'];

        $notification->setRichMessage("Changed stack from {previous-stack} to {current-stack}", [
            'previous-stack' => [
                'type' => 'deck-board',
                'id'   => (string)$previousBoard['id'],
                'name' => $previousStack['title'],
                'link' => $this->getBoardUrl($previousBoard['id']),
            ],
            'current-stack'  => [
                'type' => 'deck-board',
                'id'   => (string)$subjectBoard['id'],
                'name' => $subjectStack['title'],
                'link' => $this->getBoardUrl($subjectBoard['id']),
            ],
        ]);

        return $notification;
    }

    private function getCardUrl(int $boardId, int $cardId): string {
        return $this->urlGenerator->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
    }

    private function getBoardUrl(int $boardId): string {
        return $this->urlGenerator->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $boardId]);
    }

    /**
     * @throws \Exception
     */
    public static function make(INotification $notification, IL10N $l): INotification
    {
        return OC::$server->get(self::class)->handle($notification, $l);
    }
}
