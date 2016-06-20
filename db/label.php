<?php
// db/author.php
namespace OCA\Deck\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Label extends Entity implements JsonSerializable {

    public $id;
    protected $title;
    protected $color;
    protected $boardId;
    protected $cardId;
    public function __construct() {
        $this->addType('id','integer');
    }
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'boardId' => $this->boardId,
            'cardId' => $this->cardId,
            'color' => $this->color,
        ];
    }
}