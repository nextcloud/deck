<?php
// db/author.php
namespace OCA\Deck\Db;

use JsonSerializable;

class Card extends Entity implements JsonSerializable {

    public $id;
    protected $title;
    protected $description;
    protected $stackId;
    protected $type;
    protected $lastModified;
    protected $createdAt;
    protected $labels;
    protected $owner;
    protected $order;
    public function __construct() {
        $this->addType('id','integer');
        $this->addType('stackId','integer');
        $this->addType('order','integer');
    }

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'lastModified' => $this->lastModified,
            'createdAt' => $this->createdAt,
            'owner' => $this->owner,
            'order' => $this->order,
            'stackId' => $this->stackId,
            'labels' => $this->labels,
        ];
    }
}