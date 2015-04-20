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
4. Try to match openings and closings of blocks. These can be nested as well.
    Note that blocks can be opened and closed by the same string, in this case you cannot directly nest these within eachother, as there is no distinction between having the two blocks nested or having the blocks side by side.
    In this case, the parser will decide to close the current block rather than opening a new block within the current block.

Parser
------
Todo…

Function Reference
==================
Todo…

Language Definition Language Reference
======================================
Todo…
