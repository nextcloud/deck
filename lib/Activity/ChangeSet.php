<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Activity;

class ChangeSet implements \JsonSerializable {
	private $before;
	private $after;
	private $diff = false;

	public function __construct($before = null, $after = null) {
		if ($before !== null) {
			$this->setBefore($before);
		}
		if ($after !== null) {
			$this->setAfter($after);
		}
	}

	public function enableDiff() {
		$this->diff = true;
	}

	public function getDiff() {
		return $this->diff;
	}

	public function setBefore($before) {
		if (is_object($before)) {
			$this->before = clone $before;
		} else {
			$this->before = $before;
		}
	}

	public function setAfter($after) {
		if (is_object($after)) {
			$this->after = clone $after;
		} else {
			$this->after = $after;
		}
	}

	public function getBefore() {
		return $this->before;
	}

	public function getAfter() {
		return $this->after;
	}

	public function jsonSerialize(): array {
		return [
			'before' => $this->getBefore(),
			'after' => $this->getAfter(),
			'diff' => $this->getDiff(),
			'type' => get_class($this->before)
		];
	}
}
