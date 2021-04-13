<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Search\Query;

class SearchQuery {
	public const COMPARATOR_EQUAL = 1;
	
	public const COMPARATOR_LESS = 2;
	public const COMPARATOR_MORE = 4;
	
	public const COMPARATOR_LESS_EQUAL = 3;
	public const COMPARATOR_MORE_EQUAL = 5;

	/** @var string[] */
	private $textTokens = [];
	/** @var StringQueryParameter[] */
	private $title = [];
	/** @var StringQueryParameter[] */
	private $description = [];
	/** @var StringQueryParameter[] */
	private $stack = [];
	/** @var StringQueryParameter[] */
	private $tag = [];
	/** @var StringQueryParameter[] */
	private $assigned = [];
	/** @var DateQueryParameter[] */
	private $duedate = [];


	public function addTextToken(string $textToken): void {
		$this->textTokens[] = $textToken;
	}

	public function getTextTokens(): array {
		return $this->textTokens;
	}
	
	public function addTitle(StringQueryParameter $title): void {
		$this->title[] = $title;
	}
	
	public function getTitle(): array {
		return $this->title;
	}

	public function addDescription(StringQueryParameter $description): void {
		$this->description[] = $description;
	}

	public function getDescription(): array {
		return $this->description;
	}

	public function addStack(StringQueryParameter $stack): void {
		$this->stack[] = $stack;
	}

	public function getStack(): array {
		return $this->stack;
	}

	public function addTag(StringQueryParameter $tag): void {
		$this->tag[] = $tag;
	}

	public function getTag(): array {
		return $this->tag;
	}

	public function addAssigned(StringQueryParameter $assigned): void {
		$this->assigned[] = $assigned;
	}

	public function getAssigned(): array {
		return $this->assigned;
	}

	public function addDuedate(DateQueryParameter $date): void {
		$this->duedate[] = $date;
	}

	public function getDuedate(): array {
		return $this->duedate;
	}
}
