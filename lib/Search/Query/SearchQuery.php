<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
