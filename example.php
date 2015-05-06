<?php
include 'Parser.php';
include 'ParseException.php';
include 'Tokenizer.php';
include 'LanguageParser.php';

$parser = new parser\LanguageParser('example.ldf');
$parsed = $parser->parse('example.expression');
print_r($parsed);
