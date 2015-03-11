<?php
namespace parser;

include 'Tokenizer.php';
include 'Parser.php';
include 'ParseException.php';

class LDFReader {
	private static $tokenizer;
	private static $parser;

	private static function getTokenizer() {
		if(LDFReader::$tokenizer == null) {
			$tokenizer = new Tokenizer();
			$tokenizer->addBoundary(Tokenizer::$WHITESPACE, true);
			$tokenizer->addKeywords(array('TOKENS', 'BLOCKS', 'REWRITERULES'));
			$tokenizer->addKeywords(array('keyword', 'keywords', 'construct', 'constructs', 'block'));
			$tokenizer->addKeywords(array('ignore', 'whitespace', 'newline', 'space', 'tab', 'end', 'block', 'string', 'varchar', 'none', 'whitespace', 'file'));
			$tokenizer->addConstructs(array(',', '$', ':', '->'));
			$tokenizer->addString('\'','\'');
			$tokenizer->addBlock('(', ')');
			LDFReader::$tokenizer = $tokenizer;
		}
		return LDFReader::$tokenizer;
	}

	private static function getParser() {
		if(LDFReader::$parser == null) {
			$parser = new Parser();

			// rewrite rules for general structure
			$parser->addRewriteRule('start',array(array('keyword', 'TOKENS')), function($tokens) {return array();}, 'tokens');
			$parser->addRewriteRule('tokens',array(), function($tokens) {return array();}, 'start');
			$parser->addRewriteRule('start',array(array('keyword', 'BLOCKS')), function($tokens) {return array();}, 'blocks');
			$parser->addRewriteRule('blocks',array(), function($tokens) {return array();}, 'start');
			$parser->addRewriteRule('start',array(array('keyword', 'REWRITERULES')), function($tokens) {return array();}, 'rewrites');
			$parser->addRewriteRule('rewrites',array(), function($tokens) {return array();}, 'start');
			$parser->addRewriteRule('start', array(array('end', 'file')), function($tokens) {return array();},'end');

			// token rewrite rules
			LDFReader::addTokenRewriteRules($parser);

			// block rewrite rules
			$parser->addRewriteRule('blocks',
				array(array('varchar'), array('construct', ':'), array('string'), array('construct', '->'), array('varchar')),
				function($tokens) {return array(array('parser', 'blockrule', $tokens[0], $tokens[2], $tokens[4])); },
				'blocks');

			// rewrite rewrite rules
			LDFReader::addRewriteRewriteRules($parser);

			LDFReader::$parser = $parser;
		}
		return LDFReader::$parser;
	}

	private static function addTokenRewriteRules($parser) {
		// add ignore rewrite rules
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'whitespace')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'whitespace')); },
			'tokens');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'newline')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'newline')); },
			'tokens');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'tab')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'tab')); },
			'tokens');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'space')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'space')); },
			'tokens');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('varchar')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'varchar', $tokens[1])); },
			'tokens');

		// add keyword rewrite rules
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'keyword')),
			function($tokens) {return array(); },
			'keywords');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'keywords')),
			function($tokens) {return array(); },
			'keywords');
		$parser->addRewriteRule(
			'keywords',
			array(array('string')),
			function($tokens) {return array(array('tokenizer', 'keyword', $tokens[0]));},
			'keywordEnd');
		$parser->addRewriteRule(
			'keywordEnd',
			array(array('construct', ',')),
			function($tokens) {return array(); },
			'keywords');
		$parser->addRewriteRule(
			'keywordEnd',
			array(),
			function($tokens) {return array(); },
			'tokens');

		// add construct rewrite rules
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'construct')),
			function($tokens) {return array(); },
			'constructs');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'constructs')),
			function($tokens) {return array(); },
			'constructs');
		$parser->addRewriteRule(
			'constructs',
			array(array('string')),
			function($tokens) {return array(array('tokenizer', 'construct', $tokens[0]));},
			'constructEnd');
		$parser->addRewriteRule(
			'constructEnd',
			array(array('construct', ',')),
			function($tokens) {return array(); },
			'constructs');
		$parser->addRewriteRule(
			'constructEnd',
			array(),
			function($tokens) {return array(); },
			'tokens');

		// add block and string rewrite rules
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'block'), array('string'), array('string')),
			function($tokens) {return array(array('tokenizer', 'block', $tokens[1], $tokens[2]));},
			'tokens');
		$parser->addRewriteRule(
			'tokens',
			array(array('keyword', 'string'), array('string'), array('string')),
			function($tokens) {return array(array('tokenizer', 'string', $tokens[1], $tokens[2]));},
			'tokens');
	}

	public static function addRewriteRewriteRules($parser) {
		// basic functioning
		$parser->addRewriteRule(
			'rewrites',
			array(array('varchar'), array('construct', ':'), array('block', '('), array('construct', '->')),
			function($tokens) {return array(array('parser', 'rewriteSource', $tokens[0], $tokens[2]));},
			'rewriteDestination');
		$parser->addRecurseRule('rewrites', '(', 'rewriteSourceArrayStart');

		$parser->addRewriteRule(
			'rewriteDestination',
			array(array('varchar'), array('construct', ':'), array('keyword', 'none')),
			function($tokens) {return array(array('parser', 'rewriteDestination', $tokens[0], 'none'));},
			'rewrites');
		$parser->addRewriteRule(
			'rewriteDestination',
			array(array('varchar'), array('construct', ':'), array('block', '(')),
			function($tokens) {return array(array('parser', 'rewriteDestination', $tokens[0], $tokens[2]));},
			'rewrites');
		$parser->addRecurseRule('rewriteDestination', '(', 'rewriteDestinationArrayStart');

		// contents of source array
		$parser->addRewriteRule( // empty
			'rewriteSourceArrayStart',
			array(array('end', 'block')),
			function($tokens) {return array();},
			'end');
		$parser->addRewriteRule( // not empty
			'rewriteSourceArrayStart',
			array(),
			function($tokens) {return array();},
			'rewriteSourceArray');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'keyword'), array('string')),
			function($tokens) {return array(array('keyword', $tokens[1])); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'block'), array('string')),
			function($tokens) {return array(array('block', $tokens[1])); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('string')),
			function($tokens) {return array(array('construct', $tokens[0])); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'varchar')),
			function($tokens) {return array(array('varchar')); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'end'), array('keyword', 'file')),
			function($tokens) {return array(array('end', 'file')); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'end'), array('keyword', 'block')),
			function($tokens) {return array(array('end', 'block')); },
			'rewriteSourceArrayEnd');
		$parser->addRewriteRule(
			'rewriteSourceArrayEnd',
			array(array('construct', ',')),
			function($tokens) {return array();},
			'rewriteSourceArray');
		$parser->addRewriteRule(
			'rewriteSourceArrayEnd',
			array(array('end', 'block')),
			function($tokens) {return array();},
			'end');

		// contents of destination array
		$parser->addRewriteRule(
			'rewriteDestinationArrayStart',
			array(array('end', 'block')),
			function($tokens) {return array(); },
			'end');
		$parser->addRewriteRule(
			'rewriteDestinationArrayStart',
			array(),
			function($tokens) {return array(); },
			'rewriteDestinationArray');
		$parser->addRewriteRule(
			'rewriteDestinationArray',
			array(array('string')),
			function($tokens) {return array(array('string', $tokens[0])); },
			'rewriteDestinationArrayEnd');
		$parser->addRewriteRule(
			'rewriteDestinationArray',
			array(array('construct', '$'), array('varchar')),
			function($tokens) {return array(array('backref', $tokens[1])); },
			'rewriteDestinationArrayEnd');
		$parser->addRewriteRule(
			'rewriteDestinationArrayEnd',
			array(array('construct', ',')),
			function($tokens) {return array();},
			'rewriteDestinationArray');
		$parser->addRewriteRule(
			'rewriteDestinationArrayEnd',
			array(),
			function($tokens) {return array();},
			'end');
	}

	private static function createTokenizer($parsed) {
		$tokenizer = new Tokenizer();
		foreach($parsed as $rule) { // ('tokenizer', 'instruction', 'value'...)
			if($rule[0] == 'tokenizer') {
				switch($rule[1]) {
				case 'ignore':
					switch($rule[2]) {
					case 'whitespace':
						$tokenizer->addBoundary(Tokenizer::$WHITESPACE, true);
						break;
					case 'newline':
						$tokenizer->addBoundary(Tokenizer::$NEWLINE, true);
						break;
					case 'tab':
						$tokenizer->addBoundary(Tokenizer::$TAB, true);
						break;
					case 'space':
						$tokenizer->addBoundary(Tokenizer::$SPACE, true);
						break;
					case 'varchar':
						$tokenizer->addBoundary($rule[3]);
						break;
					default:
						throw new \Exception("Unknown ignore rule: {$rule[2]}");
						break;
					}
					break;

					case 'keyword':
						$tokenizer->addKeyword($rule[2]);
						break;
					case 'construct':
						$tokenizer->addConstruct($rule[2]);
						break;
					case 'block':
						$tokenizer->addBlock($rule[2], $rule[3]);
						break;
					case 'string':
						$tokenizer->addString($rule[2], $rule[3]);
						break;
					default:
						throw new \Exception("Unkown tokenizer rule: {$rule[1]}");
						break;
				}
			}
		}
		return $tokenizer;
	}

	private static function createParser($parsed) {
		$parser = new Parser();

		$rewriteSource = null;
		foreach($parsed as $rule) {
			if($rule[0] == 'parser') {
				switch($rule[1]) {
				case 'rewriteSource':
					if($rewriteSource != null) {
						throw new \Exception('Found two consecutive rewrite source rules');
					}

					$rewriteSource = new \stdClass();
					$rewriteSource->state = $rule[2];
					$rewriteSource->tokens = $rule[3];
					break;
				case 'rewriteDestination':
					if($rewriteSource == null) {
						throw new \Exception('Found rewrite destination rule without rewrite source rule');
					}

					$destinationFunction = LDFReader::getRewriteFunction($rule[3]);
					$destinationState = $rule[2];
					if($destinationState == 'final') {
						$destinationState = 'end';
					}

					$parser->addRewriteRule($rewriteSource->state,
						$rewriteSource->tokens,
						$destinationFunction,
						$destinationState);

					$rewriteSource = null;
					break;
				case 'blockrule':
					$parser->addRecurseRule($rule[2], $rule[3], $rule[4]);
					break;
				default:
					throw new \Exception("Unknown parser rule: {$rule[1]}");
					break;
				}
			}
		}

		return $parser;
	}

	private static function getRewriteFunction($rules) {
		if($rules == 'none') {
			return function($tokens) {return array(); };
		} else {
			// check rules for consistency
			foreach($rules as $rule) {
				if($rule[0] == 'backref') {
					if(!is_numeric($rule[1]) || 0 > (int)($rule[1])) {
						throw new ParseException("Invalid backref ID", 'an integer with value 0 or larger', $rule[1]);
					}
				}
			}

			// create function
			return function($tokens) use ($rules) {
				$toReturn = array();
				foreach($rules as $rule) {
					if($rule[0] == 'string') {
						$toReturn[] = $rule[1];
					} elseif($rule[0] == 'backref') {
						$index = (int)$rule[1];
						$toReturn[] = $tokens[$index];
					}
				}
				return array($toReturn);
			};
		}
	}


	public static function readDefinition($filename) {
		$ast = LDFReader::tokenizeString(file_get_contents($filename));
		$parsed = LDFReader::parseAst($ast);
		$tokenizer = LDFReader::createTokenizer($parsed);
		$parser = LDFReader::createParser($parsed);
		return array('ast' => $ast, 'parsed'=>$parsed, 'tokenizer'=>$tokenizer, 'parser'=>$parser);
	}
	public static function tokenizeString($string) {
		return LDFReader::getTokenizer()->tokenize($string);
	}
	public static function parseAst($ast) {
		return LDFReader::getParser()->parse($ast);
	}

}

