<?php
/**
 * Tokenizer class, to turn a string into a list of tokens
 */
namespace parser;

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
	private $blockBoundaries;
	private $blockIgnores;
	private $constructs;
	private $keywords;

	// constructor
	public function __construct() {
		$this->excludedTokens = array();
		$this->includedTokens = array();
		$this->blockOpens = array();
		$this->blockCloses = array();
		$this->blockBoundaries = array();
		$this->inertBlocks = array();
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

	public function addBlock($open, $close, $inert = false) {
		if($open == $close) {
			$this->includedTokens[] = $this->escapeBoundary($open);
			$this->blockBoundaries[] = $open;
		} else {
			$this->includedTokens[] = $this->escapeBoundary($open);
			$this->includedTokens[] = $this->escapeBoundary($close);
			$this->blockOpens[] = $open;
			$this->blockCloses[] = $close;
		}
		if($inert) {
			$this->inertBlocks[] = $open;
		}
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
		if(in_array($part, $this->blockBoundaries)) {
			return array('blockBoundary', $part);
		}
		return array('varchar', $part);
	}

	private function openBlock($blockType, &$blockStack, &$blockDepth, &$blockOpenStack) {
		$blockContents = array();
		$block = array('block', $blockType, &$blockContents);
		$blockStack[$blockDepth][] = $block;
		$blockDepth++;
		$blockStack[$blockDepth] =& $blockContents;
		$blockOpenStack[$blockDepth] = $blockType;
		unset($blockContents);
	}

	private function closeBlock(&$blockStack, &$blockDepth, &$blockOpenStack) {
		$blockStack[$blockDepth][] = array('end', 'block');
		unset($blockStack[$blockDepth]);
		unset($blockOpenStack[$blockDepth]);
		$blockDepth--;
	}


	public function tokenize($string) {
		$parts = $this->scan($string);
		$tokens = array_map(array($this, 'analyze'), $parts);
		$ast = array();
		$blockStack = array(&$ast);
		$blockOpenStack = array('');
		$blockDepth = 0;
		foreach($tokens as $token) {
			// check if we're in an inert block
			if(in_array($blockOpenStack[$blockDepth], $this->inertBlocks)) {
				$blockOpen = $blockOpenStack[$blockDepth];
				if(in_array($blockOpen, $this->blockOpens)) {
					$expectedClose = $this->blockCloses[array_search($blockOpen, $this->blockOpens)];
					$expectedType = 'blockClose';
				} else {
					$expectedClose = $blockOpen;
					$expectedType = 'blockBoundary';
				}
				if($token[0] != $expectedType  || $token[1] != $expectedClose) {
					$token[0] = 'varchar';
				}
			}

			switch($token[0]){
			case 'blockBoundary':
				$blockType = $token[1];
				if($blockOpenStack[$blockDepth] == $blockType) {
					$this->closeBlock($blockStack, $blockDepth, $blockOpenStack);
				} else {
					$this->openBlock($blockType, $blockStack, $blockDepth, $blockOpenStack);
				}
				break;
			case 'blockOpen':
				$this->openBlock($token[1], $blockStack, $blockDepth, $blockOpenStack);
				break;
			case 'blockClose':
				// check if block is correct
				$blockOpen = $blockOpenStack[$blockDepth];
				$expected = $this->blockCloses[array_search($blockOpen, $this->blockOpens)];
				if($expected != $token[1]) {
					throw new ParseException('Mismatched block closing!', $token[1], $expected);
				} else {
					// close block
					$this->closeBlock($blockStack, $blockDepth, $blockOpenStack);
				}
				break;
			default:
				$blockStack[$blockDepth][] = $token;
				break;
			}
		}
		$ast[] = array('end', 'file');
		return $ast;
	}

	public function tokenizeFile($filename) {
		return $this->tokenize(file_get_contents($filename));
	}

}

