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

// db/author.php
namespace OCA\Deck\Db;

use DateTime;
use JsonSerializable;

class Card extends RelationalEntity implements JsonSerializable {

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
	protected $archived = false;
	protected $duedate = null;

	const DUEDATE_FUTURE = 0;
	const DUEDATE_NEXT = 1;
	const DUEDATE_NOW = 2;
	const DUEDATE_OVERDUE = 3;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('stackId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('archived', 'boolean');
		$this->addRelation('labels');
		$this->addResolvable('owner');
	}

	public function jsonSerialize() {
		$json = parent::jsonSerialize();
		$json['overdue'] = self::DUEDATE_FUTURE;
		$due = strtotime($this->duedate);

		$today = new DateTime();
		$today->setTime( 0, 0, 0 );

		$match_date = new DateTime($this->duedate);

		$match_date->setTime( 0, 0, 0 );

		$diff = $today->diff( $match_date );
		$diffDays = (integer)$diff->format( "%R%a" ); // Extract days count in interval

		if($due !== false) {
			if ($diffDays === 1) {
				$json['overdue'] = self::DUEDATE_NEXT;
			}
			if ($diffDays === 0) {
				$json['overdue'] = self::DUEDATE_NOW;
			}
			if ($diffDays < 0) {
				$json['overdue'] = self::DUEDATE_OVERDUE;
			}
		}
		return $json;
	}

}
