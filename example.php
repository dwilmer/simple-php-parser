<?php
include 'Parser.php';
include 'ParseException.php';
include 'Tokenizer.php';
include 'LanguageParser.php';

try {
	$parser = new parser\LanguageParser('example.ldf');
	$parsed = $parser->parse('example.expression');
	print_r($parsed);
} catch(parser\ParseException $ex) {
	echo "Parse exception: {$ex->getMessage()}\n";
	echo "- - - - - - - -\n";
	echo "Expected: {$ex->getExpected()}\n";
	echo "Actual: {$ex->getActual()}\n";
}
