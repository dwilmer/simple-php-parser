<?php
/**
 * ParseException class, for exceptions during parsing
 */

namespace parser;

class ParseException extends \Exception {
	protected $expected;
	protected $actual;

	public function __construct($message, $expected, $actual) {
		parent::__construct($message);
		$this->expected = $expected;
		$this->actual = $actual;
	}

	public function getExpected() {
		return $this->expected;
	}

	public function getActual() {
		return $this->actual;
	}
}
