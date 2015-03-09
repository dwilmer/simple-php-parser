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
			$tokenizer->addKeywords(array('ignore', 'keyword', 'construct', 'constructs', 'block', 'inert', 'varchar', 'none', 'whitespace'));
			$tokenizer->addConstructs(array(',', '$', ':', '->'));
			$tokenizer->addBlock('\'', '\'', true);
			$tokenizer->addBlock('(', ')');
			LDFReader::$tokenizer = $tokenizer;
		}
		return LDFReader::$tokenizer;
	}

	public static function readDefinition($filename) {
		$ast = LDFReader::tokenizeString(file_get_contents($filename));
		return $ast;
	}
	public static function tokenizeString($string) {
		return LDFReader::getTokenizer()->tokenize($string);
	}

}


