<?php

namespace OCA\Deck\Service;

class CardArchivedException extends \Exception {
	/**
	 * Constructor
	 * @param string $msg the error message
	 */
	public function __construct($msg){
		parent::__construct($msg);
	}
}