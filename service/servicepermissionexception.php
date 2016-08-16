<?php

namespace OCA\Deck\Service;

class ServicePermissionException extends \Exception {
	/**
	 * Constructor
	 * @param string $msg the error message
	 */
	public function __construct($msg){
		parent::__construct($msg);
	}
}