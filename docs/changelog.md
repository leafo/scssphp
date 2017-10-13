---
layout: default
title: Changelog
---

## Changelog

* **0.7.1** -- October 13, 2017
  * Server moved to example/ folder
  * Server::serveFrom() helper removed
  * Removed .phar build
  * Workaround {% raw %}each(){% endraw %} deprecated in PHP 7.2RC (marinaglancy)
* **0.6.7** -- February 23, 2017
  * fix list interpolation
  * pscss: enable --line-numbers and --debug-info for stdin
  * checkRange() throws RangeException
* **0.6.6** -- September 10, 2016
  * Do not extend decorated tags with another tag (FMCorz)
  * Merge shared direct relationship when extending (FMCorz)
  * Extend resolution was generating invalid selectors (FMCorz)
  * Resolve function arguments using mixin content scope (FMCorz)
  * Let {% raw %}@{% endraw %}content work when a block isn’t passed in. (diemer)
* **0.6.5** -- June 20, 2016
  * ignore BOM (nwiborg)
  * fix another mixin and variable scope issue (mahagr)
  * Compiler: coerceValue support for #rgb values (thesjg)
  * preserve un-normalized variable name for error message (kissifrot)
* **0.6.4** -- June 15, 2016
  * parsing multiple assignment flags (Limych)
  * {% raw %}@{% endraw %}warn should not write to stdout (atomicalnet)
  * evaluating null and/or 'foo' (micranet)
  * case insensitive directives regression (Limych)
  * Compiler: scope change to some properties and methods to facilitate subclassing (jo)
* **0.6.3** -- January 14, 2016
  * extend + parent + placeholder fix (atna)
  * nested content infinite loop (Lusito)
  * only divide by 100 if percent (jkrehm)
  * Parser: refactoring and performance optimizations (oyejorge)
* **0.6.2** -- December 16, 2015
  * bin/pscss --iso8859-1
  * add rebeccapurple (from css color draft)
  * improve utf-8 support
* **0.6.1** -- December 13, 2015
  * bin/pscss --continue-on-error
  * fix BEM and {% raw %}@{% endraw %}extend infinite loop
  * Compiler: setIgnoreErrors(boolean)
  * exception refactoring
  * implement {% raw %}@{% endraw %}extend !optional and `keywords($args)` built-in
* **0.6.0** -- December 5, 2015
  * handle escaped quotes inside quoted strings (with and without interpolation present)
  * Compiler: undefined sourceParser when re-using a single Compiler instance
  * Parser: `getLineNo()` removed
* **0.5.1** -- November 11, 2015
  * {% raw %}@{% endraw %}scssphp-import-once
  * avoid notices with custom error handlers that don't check if `error_reporting()` returns 0
* **0.5.0** -- November 11, 2015
  * Raise minimum supported version to PHP 5.4
  * Drop HHVM support/hacks
  * Remove deprecated classmap.php
  * Node\Number units reimplemented as array
  * Compiler: treat `! null === true`
  * Compiler: `str-splice()` fixes
  * Node\Number: fixes incompatible units
* **0.4.0** -- November 8, 2015
  * Parser: remove deprecated `show()` and `to()` methods
  * Parser, Compiler: convert stdClass to Block, Node, and OutputBlock abstractions
  * New control directives: {% raw %}@{% endraw %}break, {% raw %}@{% endraw %}continue, and naked {% raw %}@{% endraw %}return
  * New operator: {% raw %}<=>{% endraw %} (spaceship) operator
  * Compiler: `index()` - coerce first argument to list
  * Compiler/Parser: fix {% raw %}@{% endraw %}media nested in mixin
  * Compiler: output literal string instead of division-by-zero exception
  * Compiler: `str-slice()` - handle negative index
  * Compiler: pass kwargs to built-ins and user registered functions as 2nd argument (instead of Compiler instance)
* **0.3.3** -- October 23, 2015
  * Compiler: add `getVariables()` and `addFeature()` API methods
  * Compiler: can pass negative indices to `nth()` and `set-nth()`
  * Compiler: can pass map as args to mixin expecting varargs
  * Compiler: add coerceList(map)
  * Compiler: improve {% raw %}@{% endraw %}at-root support
  * Nested formatter: suppress empty blocks
* **0.3.2** -- October 4, 2015
  * Fix {% raw %}@{% endraw %}extend behavior when interpolating a variable that contains a selector list
  * Hoist {% raw %}@{% endraw %}keyframes so children selectors are not prefixed by parent selector
  * Don't wrap {% raw %}@{% endraw %}import inside {% raw %}@{% endraw %}media query
  * Partial {% raw %}@{% endraw %}at-root support; `with:` and `without:` not yet supported
  * Partial `call()` support; `kwargs` not yet supported
  * String-based keys mismatch in map functions
  * Short-circuit evaluation for `and`, `or`, and `if()`
  * Compiler: getParsedFiles() now includes the main file
* **0.3.1** -- September 11, 2015
  * Fix bootstrap v4-dev regression from 0.3.0
* **0.3.0** -- September 6, 2015
  * Compiler getParsedFiles() now returns a map of imported files and their corresponding timestamps
  * Fix multiple variable scope bugs, including {% raw %}@{% endraw %}each
  * Fix regression from 0.2.1
* **0.2.1** -- September 5, 2015
  * Fix map-get(null)
  * Fix nested function definition (variable scoping)
  * Fix extend bug with BEM syntax
  * Fix selector regression from 0.1.9
* **0.2.0** -- August 25, 2015
  * Smaller git archives
  * Detect {% raw %}@{% endraw %}import loops
  * Doc blocks everywhere!
* **0.1.10** -- August 23, 2015
  * Fix 3 year old {% raw %}@{% endraw %}extend bug
  * Fix autoloader. (ext)
* **0.1.9** -- August 1, 2015
  * Adoption of the Sass Community Guidelines
  * Nested selector fixes with lists, interpolated string, and parent selector
  * Implement list-separator() and set-nth() built-ins
  * Implement {% raw %}@{% endraw %}warn and {% raw %}@{% endraw %}error
  * Removed spaceship operator pending discussion with reference implementators
* **0.1.8** -- July 18, 2015
  * Online documentation moved to http://leafo.github.com/scssphp/
  * Fix index() - map support; now returns null (instead of false) when value not found
  * Fix lighten(), darken() - percentages don't require % unit
  * Fix str-slice() - edge cases when starts-at or ends-at is 0
  * Fix type-of() - returns arglist for variable arguments
  * Fix !=
  * Fix {% raw %}@{% endraw %}return inside {% raw %}@{% endraw %}each
  * Add box support to generate .phar
* **0.1.7** -- July 1, 2015
  * bin/pscss: added --line-numbers and --debug-info options
  * Compiler: added setLineNumberStyle() and 'q' unit
  * Parser: deprecated show() and to() methods
  * simplified licensing (MIT)
  * refactoring internals and misc bug fixes (maps, empty list, function-exists())
* **0.1.6** -- June 22, 2015
  * !global
  * more built-in functions
  * Server: checkedCachedCompile() (zimzat)
  * Server: showErrorsAsCSS() to display errors in a pseudo-element (khamer)
  * misc bug fixes
* **0.1.5** -- June 2, 2015
  * misc bug fixes
* **0.1.4** -- June 2, 2015
  * add new string functions (okj579)
  * add compileFile() and checkCompile() (NoxNebula, saas786, panique)
  * fix regular expression in findImport() (lucvn)
  * needsCompile() shouldn't compare meta-etag with browser etag (edwinveldhuizen)
* **0.1.3** -- May 31, 2015
  * map support (okj579)
  * misc bug fixes (etu, bgarret, aaukt)
* **0.1.1** -- Aug 12, 2014
  * add stub classes -- a backward compatibility layer (vladimmi)
* **0.1.0** -- Aug 9, 2014
  * raise PHP requirement (5.3+)
  * reformat/reorganize source files to be PSR-2 compliant
* **0.0.15** -- Aug 6, 2014
  * fix regression with default values in functions (torkiljohnsen)
* **0.0.14** -- Aug 5, 2014
  * {% raw %}@{% endraw %}keyframes $name - didn't work inside mixin (sergeylukin)
  * Bourbon transform(translateX()) didn't work (dovy and greynor)
* **0.0.13** -- Aug 4, 2014
  * handle If-None-Match in client request, and send ETag in response (NSmithUK)
  * normalize quotation marks (NoxNebula)
  * improve handling of escape sequence in selectors (matt3224)
  * add "scss_formatter_crunched" which strips comments
  * internal: generate more accurate parse tree
* **0.0.12** -- July 6, 2014
  * revert erroneous import-partials-fix (smuuf)
  * handle If-Modified-Since in client request, and send Last-Modified in response (braver)
  * add hhvm to travis-ci testing
* **0.0.11** -- July 5, 2014
  * support multi-line continuation character (backslash) per CSS2.1 and CSS3 spec (caiosm1005)
  * imported partials should not be compiled (squarestar)
  * add setVariables() and unsetVariable() to interface (leafo/lessphp)
  * micro-optimizing is_null() (Yahasana)
* **0.0.10** -- April 14, 2014
  * fix media query merging (timonbaetz)
  * inline if should treat null as false (wonderslug)
  * optimizing toHSL() (jfsullivan)
* **0.0.9** -- December 23, 2013
  * fix {% raw %}@{% endraw %}for/{% raw %}@{% endraw %}while inside {% raw %}@{% endraw %}content block (sergeylukin)
  * fix functions in mixin_content (timonbaetz)
  * fix infinite loop when target extends itself (oscherler)
  * fix function arguments are lost inside of {% raw %}@{% endraw %}content block
  * allow setting number precision (kasperisager)
  * add public function helpers (toBool, get, findImport, assertList, assertColor, assertNumber, throwError) (Burgov, atdt)
  * add optional cache buster prefix to serve() method (iMoses)
* **0.0.8** -- September 16, 2013
  * Avoid IE7 content: counter bug
  * Support transparent as color name
  * Recursively create cache dir (turksheadsw)
  * Fix for INPUT NOT FOUND (morgen32)
* **0.0.7** -- May 24, 2013
  * Port various fixes from leafo/lessphp.
  * Improve filter precision.
  * Parsing large image data-urls does not work.
  * Add == and != ops for colors.
  * {% raw %}@{% endraw %}if and {% raw %}@{% endraw %}while directives should treat null like false.
  * Add pscss as bin in composer.json (Christian Lück).
  * Fix !default bug (James Shannon, Alberto Aldegheri).
  * Fix mixin content includes (James Shannon, Christian Brandt).
  * Fix passing of varargs to another mixin.
  * Fix interpolation bug in expToString() (Matti Jarvinen).
* **0.0.5** -- March 11, 2013
  * Better compile time errors
  * Fix top level properties inside of a nested `{% raw %}@{% endraw %}media` (Anthon Pang)
  * Fix some issues with `{% raw %}@{% endraw %}extends` (Anthon Pang)
  * Enhanced handling of `null` (Anthon Pang)
  * Helper functions shouldn't mix with css builtins (Anthon Pang)
  * Enhance selector parsing (Guilherme Blanco, Anthon Pang)
  * Add Placeholder selector support (Martin Hasoň)
  * Add variable argument support (Martin Hasoň)
  * Add zip, index, comparable functions (Martin Hasoň)
  * A bunch of parser and bug fixes
* **0.0.4** -- Nov 3nd, 2012
  * [Import path can be a function](docs/#import-paths) (Christian Lück).
  * Correctly parse media queries with more than one item (Christian Lück).
  * Add `ie_hex_str`, `abs`, `min`, `max` functions (Martin Hasoň)
  * Ignore expressions inside of `calc()` (Martin Hasoň)
  * Improve operator evaluation (Martin Hasoň)
  * Add [`{% raw %}@{% endraw %}content`](http://sass-lang.com/docs/yardoc/file.SASS_REFERENCE.html#mixin-content) support.
  * Misc bug fixes.
* **0.0.3** -- August 2nd, 2012
  * Add missing and/or/not operators.
  * Expression evaluation happens correctly.
  * Import file caching and _partial filename support.
  * Misc bug fixes.
* **0.0.2** -- July 30th, 2012
  * SCSS server is aware of imports
  * added custom function interface
  * compressed formatter
  * wrote <a href="{{ site.baseurl }}/docs/">documentation</a>
* **0.0.1** -- July 29th, 2012 -- Initial Release
