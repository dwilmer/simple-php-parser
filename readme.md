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

For the computer scientists reading this: as far as I can tell, you can use it to decide deterministic context-free languages (DCFLs).
I am not sure if it is powerful enough to decide _all_ DCFLs, but it is definately more powerful than a Regular Grammar.

How does it work?
=================
This parser works in two stages: first there is the Tokenizer, which determines what's what and tries to match opening and closing blocks.
Then there is the Parser, which you use to make sense of the input and turn it into a manageable data structure.
The Parser also checks the syntax of your input file.


Tokenizer
---------
Todo…

Parser
------
Todo…

