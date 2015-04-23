This is a simple generic parser written in PHP.
That means that you can define your own language and then use this parser to read files and make sense of them — or find that there is an error somewhere in your input file.

I do now know of _any_ generic parsers in PHP, and very few generic parsers at all.
Mostly, parsers are written for a specific language (HTML and XML seem very popular in the PHP community).
There are some generic parsers available, mostly written in C.

During my university education I worked with [Spoofax](http://strategoxt.org/Spoofax), which calls itself a “Language Workbench”.
If you want to build a language and support it with every possible tool, this is what you would use.
It is very powerful, but also very large.

My goal in this project was to create a generic parser that was the opposite of Spoofax: small, lightweight, and providing only a bare minimum in terms of functionality.
Basically, using this parser is only one step away from building the whole parser yourself — but that is a very large step.
I started it because I wanted a simple parser for another project, and ended up branching it off into its own project.

For the computer scientists reading this: as far as I can tell, you can use it to decide deterministic context-free languages (DCFLs).
I am not sure if it is powerful enough to decide _all_ DCFLs (I think not), but it is definately more powerful than a Regular Grammar.

How does it work?
=================
This parser works in two stages: first there is the Tokenizer, which determines what's what and tries to match opening and closing blocks.
Then there is the Parser, which checks the syntax of the input file and rewrites it to a manageable data structure.

In order to manage this, there is the LanguageParser class.
You can use this class to read a language definition file (instead of using the class methods) and read input files to get the output of the data structure.
Of course, this class sets up its own Tokenizer and Parser to read the language definition files.
This approach, sometimes also called “eat your own dog food”, is what introduced some of the features of the parser in the first place.

Tokenizer
---------
The tokenizer analyses what is what using simple methods to split up the input file.
It does the following things:

1. Extract the strings. This is done first, because nothing inside the strings will be parsed.
2. Break the rest apart. A break happens when:
   - There is a string of characters that is to be ignored, like whitespace in English: it only separates the words, there is no meaning attached to it.
   - There is a string of characters that form a construct, like interpunction in English: it breaks apart words, but also has meaning.
      Each separate constuct is its own part.
      When there is ambiguity (for example, "->" could be one construct resembling an arrow, or two constructs being a minus and a greater-than sign), the longest single construct is chosen.
      Opening and closing of a block are also considered constructs.
3. Classify every part, to check whether it is a string, keyword, construct, or block opening or closing. If it cannot be classified as any of these, it is considered a varchar — a string of characters with no particular meaning.
    Note that constructs are used to break up parts, but keywords aren't. Take, for example, the word "nifty" and the keyword "if".
    The part is not equal to "if", so it is not classified as a keyword.
    If, instead of a keyword, we would make "if" a construct, the word "nifty" would have been broken into a varchar "n", the construct "if", and the varchar "ty".
    Also note that this classification is not context-aware: if a word is marked as keyword, it is considered a keyword wherever it is found — not just in the correct location.
4. Try to match openings and closings of blocks. These can be nested as well.
    Note that blocks can be opened and closed by the same string, in this case you cannot directly nest these within eachother, as there is no distinction between having the two blocks nested or having the blocks side by side.
    In this case, the tokenizer will decide to close the current block rather than opening a new block within the current block.
    If a closing block is found of a block that is not opened, or an opened block is never closed, the tokenizer will throw a ParseException.
5. End each block, as well as the whole file, with a special "end" token: either the end of a block or the end of the file.

Parser
------
The parser takes the output of the tokenizer and walks through it, rewriting it in the process.

To do this, the parser has a state machine that starts in the "start" state and continues until it reaches the "end" state.
For each state, there is a set of rewrite rules, which consist of three parts:
1. The tokens to be consumed. This is an array of several tokens, saying it expects certain keywords or constructs, or a varchar or a string.
2. A function determining the output, which is given these tokens as input.
3. The resulting state.

The way Parser walks through the code is as follows:

1. For the current state, it compares the expected input of the rewrite rules to the available tokens.
    Of all tokens rewrite rules that are applicable, the longest rewrite rule is chosen.
    These tokens are “consumed”, or actually a pointer is moved past these tokens, and passed as input to the output function.
    If no rewrite rules match the available tokens, a ParseException is thrown.
    Please note that, since empty transition (also known as ɛ transitions) are allowed, an endless loop can easily be created.
    In current form this is not detected, so caveat emptor.
2. If there are any blocks in this rewrite rule, the parser recurses into this block using a recurse rule.
    Recurse rules are a set of rules containing an outer state, the block type, and the inner state.
    The outer state, or the current state before applying the rewrite rule, and the block type determine the inner state, which is the start state of the parser when parsing the block contents.
    When the parser has finished parsing the block, the rewritten block is used instead of the original block as input for the function.
3. The result of the output function is concatenated with the output array.
    Therefore, anything that has to be appended has to be wrapped inside another array.
    This is useful when different rewrite rules define different aspects of an object, and you want to store these as a dictionary or map.
    This is not the case when using a language definition file: in this simple case, I chose to simply append the result.
4. Whenever the parser is in the "end" state, it will stop parsing the current part.
    This may be either a block, and it will return from parsing this block, or the whole file.
    It will stop even if it has not yet reached the end of the block or the end of the file.
    Also, when the parser has reached the end of a block or the end of a file without reaching the end state it will throw a ParseException.

Function Reference
==================
In this section I assume you have read the previous section on the inner workings.
I refer to that section to explain each individual function's workings.

Tokenizer
---------
Constants (which are public static variables):

- `SPACE`: a single space
- `TAB`: a single tab
- `NEWLINE`: a newline
- `WHITESPACE`: any combination of whitespace

Methods:

- `addBoundary($boundary, $isRegex = false)`:  
    This function adds a boundary that is used to break up the input file into chunks.
    This boundary is not included in the chunks.
    The parameter `$isRegex` denotes that the given boundary is a regular expression, which is the case for the four constants as well.
    If it is set to `false`, the `boundary` value is escaped before using it in a regular expression.
- `addBoundaries($boundaries)`:  
    Adds an array of boundaries, similar to calling `addBoundary($boundary)` several times.
    It assumes that none of the boundaries are regular expressions.
- `addConstruct($construct)`:  
    Registers a construct, which is similar to a boundary but is included as a separate part.
- `addConstructs($constructs)`:  
    Registers an array of constructcs, calling `addConstruct` on every item of the array.
- `addBlock($open, $close)`:  
   Registers a block which is opened by the string denoted by `$open` and closed by the string given in `$close`.
- `addString($open, $close)`:  
   Registers a string which is opened by the string denoted by `$open` and closed by the string given in `$close`.
- `addKeyword($keyword)`:  
   Registers a keyword for recognising by the tokenizer.
- `addKeywords($keywords)`:  
   Registers an array of keywords by calling `addKeyword` once for each element of the `$keywords` array.
- `tokenize($string)`:  
   Reads the given string and returns a string containing all found tokens.
- `tokenizeFile($filename)`:  
   Reads the file with the given filename, and tokenizes it using the `tokenize` function.

Parser
------
Methods:

- `addRewriteRule($currentState, $consume, $produce, $nextState)`:
    This function adds a rewrite rule to the state `$currentState`.
    Both states are simple strings.
    Some remarks on the `$consume` and `$produce` arguments:
    
    + `$consume` should be an array, containing subarrays which specify the type of token that is expected.
        The first item of this subarray should be a string, either `keyword`, `construct`, `block`, `end`, `string` or `varchar`.
	The second item of this subarray should be a string indicating the contents — that is, which keyword, construct, or end.
	The keywords and constructs are the ones given, the block type is indicated by its opening, and the end can be either `block` or `file`.
	Of course, the contents of a string or varchar cannot be defined and the subarray needs only one item.
	If the rewrite rule represents an empty transition, `$consume` should be an empty array.
    + `$produce` should be a function reference (as a string) or an [anonymous function](http://php.net/manual/en/functions.anonymous.php), with the latter being preferred.
        It should take one argument, and this is an array of token values.
	That is: for all strings and varchars it is the value; for all keywords, constructs, and ends it is the type of keyword, construct or end; for blocks, it is an array containing the parsed content of the blocks.
	There is one value in the array for each token, in the order of the tokens.
	It should return an array that is then merged with the current output array.
	If, for example, you want to append a something, wrap it inside an array and return that.

- `addRecurseRule($state, $blockType, $recurseStart)`:  
    This function adds a recurse rule, as explained in the "How does it work?" section.
- `parse($ast, $startState = 'start'):`  
    This function parses the ast which is produced by the tokenizer, as explained in the "How does it work?" section.
    The `$startState` is used for recursion, but you can change it yourself if you want.
    Just remember to use the new startstate in the rewrite rules.

ParseException
--------------
This class extends [\Exception](http://php.net/manual/en/class.exception.php).

Constructors:

- `new ParseException($message, $expected, $actual)`: creates a new ParseException with the given message, a string describing an expected value and a string describing the actually found value.

Methods:

- `getExpected()`: returns the string describing the expected value.
- `getActual()`: returns the string describing the actual value.

LanguageParser
--------------
This class is a wrapper for the Tokenizer and the Parser, using a language definition file to configure them both.

Constructors:

- `new LanguageParser($languageDefinitionFile)`: reads the file specified by the string `$langueDefinitionFile` as file name.
    This is expected to be a language definition file (see below for the language specification).
    The language definition in the given file is used to configure a tokenizer and a parser, which are private.

Methods:

- `parse($inputFile)`: read the file specified by the string `$inputFile` and parses it using the tokenizer and parser.


Language Definition Language Reference
======================================
The Language Definition Language is little more than the above methods in a shorter, better readable syntax.

Basic syntax
------------
In this language, whitespace is used only as separation and is otherwise ignored.
The amount and nature of whitespace does not matter: any combination of spaces, tabs, and newlines is considered the same.

Strings are pieces of text enclosed by single quotes (`'`).

File structure
--------------
A file is divided into three parts: tokens, block recurse rules and block rewrite rules.
These are denoted by the keywords `TOKENS`, `BLOCKS` and `REWRITERULES` as a start.
The parts can be used multiple times and in any order, or left out if desired (which makes little sense, except maybe when there are no blocks for recursion).

### TOKENS
In the TOKENS block, the tokenizer is configured.
The different types of tokens are configured using the keywords `ignore`, `keyword`, `keywords`, `construct`, `constructs`, `block`, and `string`.

- `ignore`: after this keyword, either a string or one of `space`, `tab`, `newline`, or `whitespace` is expected (e.g. `ignore whitespace` or `ignore '#'`). This will be used as input for the `addBoundary` function of the Tokenizer. Note that a string is not considered a regular expression, while the newline and whitespace are.
- `keyword`/`keywords`: these can be used interchangeably, there is no actual distinction between these keywords. The `keyword`/`keywords` keyword should be followed by one or more strings, separated by commas (e.g. `keywords 'foo', 'bar'` or `keyword 'baz'`). These are the keywords of your language.
- `construct`/`constructs`: similar to `keyword`/`keywords`, these can be used interchangeable as well. The keyword should be followed by one or more strings, separated by commas (e.g. `construct '.'` or `constructs '->', ':'`). These are the constructs of your language.
- `block`: this keyword should be followed by two strings, denoting the opening and closing of a block (e.g. `block '{' '}'`).
- `string`: this keyword should be followed by two strings, denoting the opening and closing of a string (e.g. `string '<<' '>>'` or `string '"' '"'`).

### BLOCKS
In this part, the recurse rules of the parser are defined.
The syntax is as follows: `<state> : '<blocktype>' -> <recurseStart>`, where `<state>` and `<recurseStart>` are names of states and `<blocktype>` is a string, corresponding to the opening string of a block.
These are used as input for the `addRecurseRule` function of the Parser.

### REWRITERULES
In this part, the rewrite rules of the parser are defined.
The syntax is as follows:  `<startState> : (<input>) -> <endState> : (<output>)`, where `<startState>` and `<endState>` are names of states and `<input>` and `<output>` denote the expected input and the produced output.

Note that, since `end` is already a keyword, the `end` state should be denoted by the word `final`.
This is a work-around which will be fixed in a later version.

#### input
The expected input is a set of zero or more comma separated tokens defining what tokens are expected as input.
The following tokens can be used:

- `keyword <string>`: expect a keyword, for example `keyword 'foo'`.
- `block <string>`: expect a block, for example `block '('`.
- `<string>`: expect a construct, for example `'->'`.
- `varchar`: expect a varchar.
- `string`: expect a string.
- `end block`: expect the end of the current block.
- `end file`: expect the end of the file.

#### output
The output consists of one or more comma separated strings or backreferences.
This creates a tuple which will be appended to the output array.
Note that this is different from the behaviour of the `addRewriteRule` method of the Parser; this is done to keep the language simple and because I expect that the more advanced usage of the `addRewriteRule` method is not necessary most of the time.

The syntax of the backreference is a dollar sign (`$`) followed by a number.
This number is the zero-based index of the value of the desired token.
For example, when the expected input is `(varchar, '+', varchar)` we could use `('add', $0, $2)` as output.
This will create a tuple with the string "add" as first item, and the two arguments of the addition as second and third item.

For syntactic sugar, when there is no output, the whole item can be replaced by the keyword `none`.
Take for example the following rewrite rule, designed to make a free transition from the `tokens` state to the `start` state:

    tokens : () -> start: none


List of Keywords & constructs
-----------------------------
These are all keywords in the language, in alphabetical order.
They should not be used at any place other than when they're expected or in strings.

- `BLOCKS`
- `REWRITERULES`
- `TOKENS`
- `block`
- `construct`
- `constructs`
- `end`
- `file`
- `ignore`
- `keyword`
- `keywords`
- `newline`
- `none`
- `space`
- `string`
- `tab`
- `varchar`
- `whitespace`

These are all constructs in the language:

- `,`
- `$`
- `:`
- `->`

These are the constructs that form blocks:

- `(` and `)`

These are the constructs that form strings:
- `'` and `'`.


Example
-------
In the `example.ldl` file, you can find an example file for testing.



