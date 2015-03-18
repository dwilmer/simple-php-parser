<?php
namespace parser;

include 'Tokenizer.php';
include 'Parser.php';
include 'ParseException.php';

class LanguageParser {
	private static $ldfTokenizer;
	private static $ldfParser;
	private $tokenizer;
	private $parser;

	public function __construct($languageDefinitionFile) {
		$ldfAst = LanguageParser::getTokenizer()->tokenize(file_get_contents($languageDefinitionFile));
		$parsedLDF = LanguageParser::getParser()->parse($ldfAst);
		$this->tokenizer = LanguageParser::createTokenizer($parsedLDF);
		$this->parser = LanguageParser::createParser($parsedLDF);
	}

	public function parse($inputFile) {
		$inputString = file_get_contents($inputFile);
		$tokenized = $this->tokenizer->tokenize($inputString);
		return $this->parser->parse($tokenized);
	}

	private static function getTokenizer() {
		if(LanguageParser::$ldfTokenizer == null) {
			$ldfTokenizer = new Tokenizer();
			$ldfTokenizer->addBoundary(Tokenizer::$WHITESPACE, true);
			$ldfTokenizer->addKeywords(array('TOKENS', 'BLOCKS', 'REWRITERULES'));
			$ldfTokenizer->addKeywords(array('keyword', 'keywords', 'construct', 'constructs', 'block'));
			$ldfTokenizer->addKeywords(array('ignore', 'whitespace', 'newline', 'space', 'tab', 'end', 'block', 'string', 'varchar', 'none', 'whitespace', 'file'));
			$ldfTokenizer->addConstructs(array(',', '$', ':', '->'));
			$ldfTokenizer->addString('\'','\'');
			$ldfTokenizer->addBlock('(', ')');
			LanguageParser::$ldfTokenizer = $ldfTokenizer;
		}
		return LanguageParser::$ldfTokenizer;
	}

	private static function getParser() {
		if(LanguageParser::$ldfParser == null) {
			$ldfParser = new Parser();

			// rewrite rules for general structure
			$ldfParser->addRewriteRule('start',array(array('keyword', 'TOKENS')), function($tokens) {return array();}, 'tokens');
			$ldfParser->addRewriteRule('tokens',array(), function($tokens) {return array();}, 'start');
			$ldfParser->addRewriteRule('start',array(array('keyword', 'BLOCKS')), function($tokens) {return array();}, 'blocks');
			$ldfParser->addRewriteRule('blocks',array(), function($tokens) {return array();}, 'start');
			$ldfParser->addRewriteRule('start',array(array('keyword', 'REWRITERULES')), function($tokens) {return array();}, 'rewrites');
			$ldfParser->addRewriteRule('rewrites',array(), function($tokens) {return array();}, 'start');
			$ldfParser->addRewriteRule('start', array(array('end', 'file')), function($tokens) {return array();},'end');

			// token rewrite rules
			LanguageParser::addTokenRewriteRules($ldfParser);

			// block rewrite rules
			$ldfParser->addRewriteRule('blocks',
				array(array('varchar'), array('construct', ':'), array('string'), array('construct', '->'), array('varchar')),
				function($tokens) {return array(array('parser', 'blockrule', $tokens[0], $tokens[2], $tokens[4])); },
				'blocks');

			// rewrite rewrite rules
			LanguageParser::addRewriteRewriteRules($ldfParser);

			LanguageParser::$ldfParser = $ldfParser;
		}
		return LanguageParser::$ldfParser;
	}

	private static function addTokenRewriteRules($ldfParser) {
		// add ignore rewrite rules
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'whitespace')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'whitespace')); },
			'tokens');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'newline')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'newline')); },
			'tokens');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'tab')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'tab')); },
			'tokens');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('keyword', 'space')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'space')); },
			'tokens');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'ignore'), array('varchar')),
			function($tokens) {return array(array('tokenizer', 'ignore', 'varchar', $tokens[1])); },
			'tokens');

		// add keyword rewrite rules
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'keyword')),
			function($tokens) {return array(); },
			'keywords');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'keywords')),
			function($tokens) {return array(); },
			'keywords');
		$ldfParser->addRewriteRule(
			'keywords',
			array(array('string')),
			function($tokens) {return array(array('tokenizer', 'keyword', $tokens[0]));},
			'keywordEnd');
		$ldfParser->addRewriteRule(
			'keywordEnd',
			array(array('construct', ',')),
			function($tokens) {return array(); },
			'keywords');
		$ldfParser->addRewriteRule(
			'keywordEnd',
			array(),
			function($tokens) {return array(); },
			'tokens');

		// add construct rewrite rules
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'construct')),
			function($tokens) {return array(); },
			'constructs');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'constructs')),
			function($tokens) {return array(); },
			'constructs');
		$ldfParser->addRewriteRule(
			'constructs',
			array(array('string')),
			function($tokens) {return array(array('tokenizer', 'construct', $tokens[0]));},
			'constructEnd');
		$ldfParser->addRewriteRule(
			'constructEnd',
			array(array('construct', ',')),
			function($tokens) {return array(); },
			'constructs');
		$ldfParser->addRewriteRule(
			'constructEnd',
			array(),
			function($tokens) {return array(); },
			'tokens');

		// add block and string rewrite rules
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'block'), array('string'), array('string')),
			function($tokens) {return array(array('tokenizer', 'block', $tokens[1], $tokens[2]));},
			'tokens');
		$ldfParser->addRewriteRule(
			'tokens',
			array(array('keyword', 'string'), array('string'), array('string')),
			function($tokens) {return array(array('tokenizer', 'string', $tokens[1], $tokens[2]));},
			'tokens');
	}

	public static function addRewriteRewriteRules($ldfParser) {
		// basic functioning
		$ldfParser->addRewriteRule(
			'rewrites',
			array(array('varchar'), array('construct', ':'), array('block', '('), array('construct', '->')),
			function($tokens) {return array(array('parser', 'rewriteSource', $tokens[0], $tokens[2]));},
			'rewriteDestination');
		$ldfParser->addRecurseRule('rewrites', '(', 'rewriteSourceArrayStart');

		$ldfParser->addRewriteRule(
			'rewriteDestination',
			array(array('varchar'), array('construct', ':'), array('keyword', 'none')),
			function($tokens) {return array(array('parser', 'rewriteDestination', $tokens[0], 'none'));},
			'rewrites');
		$ldfParser->addRewriteRule(
			'rewriteDestination',
			array(array('varchar'), array('construct', ':'), array('block', '(')),
			function($tokens) {return array(array('parser', 'rewriteDestination', $tokens[0], $tokens[2]));},
			'rewrites');
		$ldfParser->addRecurseRule('rewriteDestination', '(', 'rewriteDestinationArrayStart');

		// contents of source array
		$ldfParser->addRewriteRule( // empty
			'rewriteSourceArrayStart',
			array(array('end', 'block')),
			function($tokens) {return array();},
			'end');
		$ldfParser->addRewriteRule( // not empty
			'rewriteSourceArrayStart',
			array(),
			function($tokens) {return array();},
			'rewriteSourceArray');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'keyword'), array('string')),
			function($tokens) {return array(array('keyword', $tokens[1])); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'block'), array('string')),
			function($tokens) {return array(array('block', $tokens[1])); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('string')),
			function($tokens) {return array(array('construct', $tokens[0])); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'varchar')),
			function($tokens) {return array(array('varchar')); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'end'), array('keyword', 'file')),
			function($tokens) {return array(array('end', 'file')); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArray',
			array(array('keyword', 'end'), array('keyword', 'block')),
			function($tokens) {return array(array('end', 'block')); },
			'rewriteSourceArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteSourceArrayEnd',
			array(array('construct', ',')),
			function($tokens) {return array();},
			'rewriteSourceArray');
		$ldfParser->addRewriteRule(
			'rewriteSourceArrayEnd',
			array(array('end', 'block')),
			function($tokens) {return array();},
			'end');

		// contents of destination array
		$ldfParser->addRewriteRule(
			'rewriteDestinationArrayStart',
			array(array('end', 'block')),
			function($tokens) {return array(); },
			'end');
		$ldfParser->addRewriteRule(
			'rewriteDestinationArrayStart',
			array(),
			function($tokens) {return array(); },
			'rewriteDestinationArray');
		$ldfParser->addRewriteRule(
			'rewriteDestinationArray',
			array(array('string')),
			function($tokens) {return array(array('string', $tokens[0])); },
			'rewriteDestinationArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteDestinationArray',
			array(array('construct', '$'), array('varchar')),
			function($tokens) {return array(array('backref', $tokens[1])); },
			'rewriteDestinationArrayEnd');
		$ldfParser->addRewriteRule(
			'rewriteDestinationArrayEnd',
			array(array('construct', ',')),
			function($tokens) {return array();},
			'rewriteDestinationArray');
		$ldfParser->addRewriteRule(
			'rewriteDestinationArrayEnd',
			array(),
			function($tokens) {return array();},
			'end');
	}

	private static function createTokenizer($parsed) {
		$ldfTokenizer = new Tokenizer();
		foreach($parsed as $rule) { // ('tokenizer', 'instruction', 'value'...)
			if($rule[0] == 'tokenizer') {
				switch($rule[1]) {
				case 'ignore':
					switch($rule[2]) {
					case 'whitespace':
						$ldfTokenizer->addBoundary(Tokenizer::$WHITESPACE, true);
						break;
					case 'newline':
						$ldfTokenizer->addBoundary(Tokenizer::$NEWLINE, true);
						break;
					case 'tab':
						$ldfTokenizer->addBoundary(Tokenizer::$TAB, true);
						break;
					case 'space':
						$ldfTokenizer->addBoundary(Tokenizer::$SPACE, true);
						break;
					case 'varchar':
						$ldfTokenizer->addBoundary($rule[3]);
						break;
					default:
						throw new \Exception("Unknown ignore rule: {$rule[2]}");
						break;
					}
					break;

					case 'keyword':
						$ldfTokenizer->addKeyword($rule[2]);
						break;
					case 'construct':
						$ldfTokenizer->addConstruct($rule[2]);
						break;
					case 'block':
						$ldfTokenizer->addBlock($rule[2], $rule[3]);
						break;
					case 'string':
						$ldfTokenizer->addString($rule[2], $rule[3]);
						break;
					default:
						throw new \Exception("Unkown tokenizer rule: {$rule[1]}");
						break;
				}
			}
		}
		return $ldfTokenizer;
	}

	private static function createParser($parsed) {
		$ldfParser = new Parser();

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

					$destinationFunction = LanguageParser::getRewriteFunction($rule[3]);
					$destinationState = $rule[2];
					if($destinationState == 'final') {
						$destinationState = 'end';
					}

					$ldfParser->addRewriteRule($rewriteSource->state,
						$rewriteSource->tokens,
						$destinationFunction,
						$destinationState);

					$rewriteSource = null;
					break;
				case 'blockrule':
					$ldfParser->addRecurseRule($rule[2], $rule[3], $rule[4]);
					break;
				default:
					throw new \Exception("Unknown parser rule: {$rule[1]}");
					break;
				}
			}
		}

		return $ldfParser;
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
}

