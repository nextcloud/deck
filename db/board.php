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
    protected $shared;

    public function __construct() {
        $this->addType('id','integer');
        $this->addType('shared','integer');
        $this->addRelation('labels');
        $this->addRelation('acl');
        $this->addRelation('shared');
        $this->shared = -1;
    }

    public function jsonSerialize() {
        $result = [
            'id' => $this->id,
            'title' => $this->title,
            'owner' => $this->owner,
            'color' => $this->color,
            'labels' => $this->labels,
            'acl' => $this->acl,
        ];
        if($this->shared!==-1) {
            $result['shared'] = $this->shared;
        }
        return $result;
    }

    public function setLabels($labels) {
        foreach ($labels as $l) {
            $this->labels[] = $l;
        }
    }

    public function setAcl($acl) {
        foreach ($acl as $a) {
            $this->acl[$a->id] = $a;
        }
    }
}