<?php
/**
 * Tokenizer class, to turn a string into a list of tokens
 */

require 'ParseException.php';

class Tokenizer {
	// constatns
	public static $SPACE = ' ';
	public static $TAB = "\t";
	public static $NEWLINE = "\r?\n";
	public static $WHITESPACE = '[\s]+';
	
	// Boundaries for Splitting / Scanning
	private $excludedTokens;
	private $includedTokens;

	// Lexical Analysis fields
	private $blockOpens;
	private $blockCloses;
	private $constructs;
	private $keywords;

	// constructor
	public function __construct() {
		$this->excludedTokens = array();
		$this->includedTokens = array();
		$this->blockOpens = array();
		$this->blockCloses = array();
		$this->constructs = array();
		$this->keywords = array();
	}

	// Functions for setting up the tokenizer
	private function escapeBoundary($boundary) {
		return str_replace(
			array('\\','.','[',']','(',')'),
			array('\\\\','\.','\[', '\]','\(','\)'), $boundary);
	}

	public function addBoundary($boundary, $isRegex = false) {
		// escape boundary
		if(!$isRegex) {
			$boundary = $this->escapeBoundary($boundary);
		}
		$this->excludedTokens[] = $boundary;
	}

	public function addBoundaries($boundaries) {
		foreach($boundaries as $boundary) {
			$this->addBoundary($boundary);
		}
	}

	public function addConstruct($construct) {
		$this->constructs[] = $construct;
		$this->includedTokens[] = $this->escapeBoundary($construct);
	}

	public function addConstructs($constructs) {
		foreach($constructs as $construct) {
			$this->addConstruct($construct);
		}
	}

	public function addBlock($open, $close) {
		$this->includedTokens[] = $this->escapeBoundary($open);
		$this->includedTokens[] = $this->escapeBoundary($close);
		$this->blockOpens[] = $open;
		$this->blockCloses[] = $close;
	}

	public function addKeyword($keyword) {
		$this->keywords[] = $keyword;
	}

	public function addKeywords($keywords) {
		$this->keywords = array_merge($this->keywords, $keywords);
	}

	// Functions for tokenizing
	private function scan($string) {
		// split on excluded tokens
		if(count($this->excludedTokens) > 0) {
			$pattern = '/(' . implode(')|(', $this->excludedTokens) . ')/';
			$subparts = preg_split($pattern, $string, null, PREG_SPLIT_NO_EMPTY);
		} else {
			$subparts = array($string);
		}

		// split on included tokens
		if(count($this->includedTokens) > 0) {
			$parts= array();
			$pattern = '/(' . implode(')|(', $this->includedTokens) . ')/';
			foreach($subparts as $subpart) {
				$newparts = preg_split($pattern, $subpart, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$parts = array_merge($parts, $newparts);
			}
		} else {
			$parts = $subparts;
		}
		return $parts;
	}

	private function analyze($part) {
		if(in_array($part, $this->keywords)) {
			return array('keyword', $part);
		}
		if(in_array($part, $this->constructs)) {
			return array('construct', $part);
		}
		if(in_array($part, $this->blockOpens)) {
			return array('blockOpen', $part);
		}
		if(in_array($part, $this->blockCloses)) {
			return array('blockClose', $part);
		}
		return array('varchar', $part);
	}

	public function tokenize($string) {
		$parts = $this->scan($string);
		$tokens = array_map(array($this, 'analyze'), $parts);
		$ast = array();
		$blockStack = array(&$ast);
		$blockOpenStack = array('');
		$blockDepth = 0;
		foreach($tokens as $token) {
			if($token[0] == 'blockOpen') {
				// create new block
				$blockContents = array();
				$block = array('block', $token[1], &$blockContents);
				$blockStack[$blockDepth][] = $block;
				$blockDepth++;
				$blockStack[$blockDepth] =& $blockContents;
				$blockOpenStack[$blockDepth] = $token[1];
			} else if($token[0] == 'blockClose') {
				// check if block is correct
				$blockOpen = $blockOpenStack[$blockDepth];
				$expected = $this->blockCloses[array_search($blockOpen, $this->blockOpens)];
				if($expected != $token[1]) {
					throw new ParseException('Mismatched block closing!', $token[1], $expected);
				}


				// close block
				$blockStack[$blockDepth][] = array('end', 'block');
				unset($blockStack[$blockDepth]);
				unset($blockOpenStack[$blockDepth]);
				$blockDepth--;
			} else {
				$blockStack[$blockDepth][] = $token;
			}
		}
		$ast[] = array('end', 'file');
		return $ast;
	}

	public function tokenizeFile($filename) {
		return $this->tokenize(file_get_contents($filename));
	}

}

