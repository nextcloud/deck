<?php
// db/author.php
namespace OCA\Deck\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Board extends Entity implements JsonSerializable {

    public $id;
    protected $title;
    protected $owner;
    protected $color;
    protected $archived;
    public function __construct() {
        $this->addType('id','integer');
    }
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'owner' => $this->owner,
            'color' => $this->color
        ];
    }
}