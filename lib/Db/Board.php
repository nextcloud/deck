<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *  
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *  
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *  
 */

namespace OCA\Deck\Db;

use JsonSerializable;

class Board extends Entity implements JsonSerializable {

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