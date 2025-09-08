<?php

declare(strict_types=1);

namespace OCA\Deck\Service;

use OCA\Deck\Db\Card;
use OCP\AppFramework\Utility\ITimeFactory;

class DeckCommentActivityService
{
    private string $prependString = '[%s] -';
    private string $dateTimeFormat = 'Y-m-d H:i:s';

    public function __construct(
        private readonly CommentService $commentService,
        private readonly ITimeFactory $timeFactory,
    ) {
        //
    }

    /**
     * @throws \OCA\Deck\BadRequestException
     * @throws \OCA\Deck\NoPermissionException
     * @throws \OCA\Deck\NotFoundException
     */
    public function cardChangesStack(Card $card, string $previousStackTitle, string $currentStackTitle): void
    {
        $this->commentToCard(
            card: $card,
            message: sprintf('%s Changed stack from %s to %s',
                $this->getPrepend(),
                $previousStackTitle,
                $currentStackTitle
            )
        );
    }

    private function getPrepend(): string
    {
        return sprintf($this->prependString, $this->timeFactory->getDateTime()->format(format: $this->dateTimeFormat));
    }

    /**
     * @throws \OCA\Deck\NoPermissionException
     * @throws \OCA\Deck\BadRequestException
     * @throws \OCA\Deck\NotFoundException
     */
    private function commentToCard(Card $card, string $message): void
    {
        $this->commentService->create(cardId: $card->getId(), message: $message);
    }
}
