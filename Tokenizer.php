<?php
/**
 * Tokenizer class, to turn a string into a list of tokens
 */

class Tokenizer {
	public static $SPACE = ' ';
	public static $TAB = "\t";
	public static $NEWLINE = "\r?\n";
	public static $WHITESPACE = '[\s]+';
	private $excludedTokens;
	private $includedTokens;

	public function __construct() {
		$this->excludedTokens = array();
		$this->includedTokens = array();
	}

	public function addBoundary($boundary, $isToken = false, $preventEscape = false) {
		// escape boundary
		if(!$preventEscape) {
			$boundary = str_replace(
				array('.','[',']','\\'),
				array('\.','\[', '\]','\\\\'), $boundary);
		}
		if($isToken) {
			$this->includedTokens[] = $boundary;
		} else {
			$this->excludedTokens[] = $boundary;
		}
	}

	public function addBoundaries($boundaries, $areTokens = false) {
		foreach($boundaries as $boundary) {
			$this->addBoundary($boundary, $areTokens);
		}
	}


	public function tokenize($string) {
		// split on excluded tokens
		if(count($this->excludedTokens) > 0) {
			$pattern = '/' . implode('|', $this->excludedTokens) . '/';
			$parts = preg_split($pattern, $string, null, PREG_SPLIT_NO_EMPTY);
		} else {
			$parts = array($string);
		}

		// split on included tokens
		if(count($this->excludedTokens) > 0) {
			$tokens = array();
			$pattern = '/(' . implode(')|(', $this->includedTokens) . ')/';
			foreach($parts as $part) {
				$newtokens = preg_split($pattern, $part, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$tokens = array_merge($tokens, $newtokens);
			}
		} else {
			$tokens = $parts;
		}
		return $tokens;
	}

	public function tokenizeFile($filename) {
		return $this->tokenize(file_get_contents($filename);
	}

}

