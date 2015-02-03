<?php

class Parser {
	private $states;
	private $rewriteRules;
	private $recurseRules;

	public function __construct() {
		$this->states = array('start', 'end');
		$this->rewriteRules = array();
	}

	public function addState($state) {
		$this->states[] = $state;
		$this->rewriteRules[$state] = array();
		$this->recurseRules[$state] = array();
	}

	public function addRewriteRule($currentState, $consume, $produce, $nextState) {
		$this->rewriteRules[$currentState][] = array($consume, $produce, $nextState);
	}

	public function addRecurseRule($state, $blockElement, $recurseStart) {
		$this->recurseRules[$state][$blockElement] = $recurseStart;
	}

	public function parse($ast, $startState = 'start') {
		// set up
		$state = $startState;
		$index = 0;
		$result = array();

		while($state != 'end') {
			// try to match rules
			$currentRules = $this->rewriteRules[$state];
			$bestMatch = $this->getBestMatch($ast, $index, $currentRules);
			
			// no match, try to be as informative as possible and throw exception
			if($bestMatch === null) {
				$this->raiseMismatch($ast, $index, $state, $currentRules);
			}

			// we have a match!
			$consume = $bestMatch[0];
			$produce = $bestMatch[1];
			$nextState = $bestMatch[2];
			$end = $index + count($consume);
			$arguments = array();
			for(; $index < $end; $index++) {
				$node = $ast[$index];
				echo "Consuming node {$this->displayNode($node)}\n";
				if($node[0] == 'block') {
					$blockState = $this->recurseRules[$state][$node[1]];
					$arguments[] = $this->parse($node[2], $blockState);
				} else {
					$arguments[] = $ast[$index][1];
				}
			}
			$rewritten = $produce($arguments);
			$result = array_merge($result, $rewritten);
			$state = $nextState;
		}
		return $result;
	}

	private function getBestMatch($ast, $astIndex, $rules) {
		$bestMatch = null;
		$bestScore = -1;
		foreach($rules as $rule) {
			$score = $this->tryMatch($ast, $astIndex, $rule[0]);
			if($score !== false && $score > $bestScore) {
				$bestMatch = $rule;
				$bestScore = $score;
			}
		}
		return $bestMatch;
	}


	private function tryMatch($ast, $astIndex, $consume) {
		$offset = 0;
		while($offset < count($consume)) {
			$astElem = $ast[$astIndex + $offset];
			$matchElem = $consume[$offset];

			if($astElem[0] != $matchElem[0]) {
				return false;
			}
			if($matchElem[0] != 'varchar' && $matchElem[1] != $astElem[1]) {
				return false;
			}
			$offset++;
		}
		return $offset;
	}

	private function raiseMismatch($ast, $astIndex, $state, $rules) {
		$message = 'Unexpected token in state ' . $state;
		if($astIndex > 0) {
			$message .= ' after ' . $this->displayNode($ast[$astIndex - 1]);
		}
		$expected = array();
		foreach($rules as $rule) {
			$toAdd = $this->displayNode($rule[0][0]);
			if(!in_array($toAdd, $expected)) {
				$expected[] = $toAdd;
			}
		}
		$actual = $this->displayNode($ast[$astIndex]);
		throw new ParseException($message, 'One of the following tokens: ' . implode(',', $expected), $actual);
	}

	private function displayNode($node) {
		if($node[0] == 'end') {
			return 'end of ' . $node[1];
		} else if($node[0] == 'varchar') {
			return 'varchar';
		} else {
			return '"' . $node[1] . '"';
		}
	}
}

