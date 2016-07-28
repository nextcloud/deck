<?php
// db/author.php
namespace OCA\Deck\Db;

use JsonSerializable;

class Board extends \OCA\Deck\Db\Entity implements JsonSerializable {

    public $id;
    protected $title;
    protected $owner;
    protected $color;
    protected $archived;
    protected $labels;
    protected $acl;

    public function __construct() {
        $this->addType('id','integer');
        $this->addRelation('labels');
        $this->addRelation('acl');
    }

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'owner' => $this->owner,
            'color' => $this->color,
            'labels' => $this->labels,
            'acl' => $this->acl,
        ];
    }

    public function setLabels($labels) {
        foreach ($labels as $l) {
            $this->labels[$l->id] = $l;
        }
    }

    public function setAcl($acl) {
        foreach ($acl as $a) {
            $this->acl[$a->id] = $a;
        }
    }
}