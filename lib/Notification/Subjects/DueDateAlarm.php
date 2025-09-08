<?php

declare(strict_types=1);

namespace OCA\Deck\Notification\Subjects;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;

class DueDateAlarm
{
    public const SUBJECT = 'due_date_alarm';

    public function __construct(
        private readonly IURLGenerator $urlGenerator,
    ) {
        //
    }

    public function handle(INotification $notification, IL10N $l): INotification
    {
        // Gather data from subject parameters
        $card = $notification->getSubjectParameters()['card'];
        $stack = $notification->getSubjectParameters()['stack'];
        $board = $notification->getSubjectParameters()['board'];

        // Create notification
        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-dark.svg')));
        $notification->setRichSubject($l->t('{card} {board} needs attention!'), [
            'card'  => [
                'type' => 'deck-card',
                'id'   => (string)$card['id'],
                'name'      => $card['title'],
                'stackname' => $stack['title'],
                'boardname' => $board['title'],
                'link'      => $this->getCardUrl($board['id'], $card['id']),
            ],
            'board' => [
                'type' => 'deck-board',
                'id'   => (string)$board['id'],
                'name' => "[{$board['title']}]",
                'link' => $this->getBoardUrl($board['id']),
            ],
        ]);
        $notification->setRichMessage($l->t('Less than 15 minutes till Due Date'));

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
        return \OC::$server->get(self::class)->handle($notification, $l);
    }
}
