---
layout: default
---

**scssphp** is a compiler for [SCSS][0] 3.x written in PHP.

SCSS is a CSS preprocessor language that adds many features like variables,
mixins, imports, color manipulation, functions, and tons of other powerful
features.

**scssphp** is ready for inclusion in any project. It includes a command
line tool for running the compiler from a terminal/shell or script.

<div class="github-buttons">
<iframe src="http://ghbtns.com/github-btn.html?user=leafo&repo=scssphp&type=watch&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="110px" height="20px"></iframe>
<iframe src="http://ghbtns.com/github-btn.html?user=leafo&repo=scssphp&type=fork&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="95px" height="20px"></iframe>
</div>

<a name="installing"></a>

## Installing

You can always download the latest version here:
<a href="{{ site.repo_url }}/archive/v{{ site.current_version }}.tar.gz" id="download-link">scssphp-{{ site.current_version }}.tar.gz</a>

You can also find the latest source online:
<{{ site.repo_url }}/>

If you use [Packagist][2] for installing packages, then you can update your `composer.json` like so:

{% highlight json %}
{
    "require": {
        "leafo/scssphp": "{{ site.current_version }}"
    }
}
{% endhighlight %}

Note: git archives of stable versions no longer include the `tests/` folder.
To install the unit tests, download the complete package source using `composer`'s
`--prefer-source` option.

<a name="quickstart"></a>

## Language Reference

For a complete guide to the syntax of SCSS, consult the [official documentation][1].

## PHP Reference

Complete documentation for **scssphp** is located at <a href="{{ site.baseurl }}docs/">{{ site.baseurl }}docs/</a>.

### Quickstart

If you just want to start serving compiled `scss` files as quick as possible
then start here.

**scssphp** comes with a easy to use class that automatically compiles modified
`scss` files and serves them from a directory you specify.

Create a file, like `style.php`:

{% highlight php startinline=true %}
require_once "scssphp/scss.inc.php";

use Leafo\ScssPhp\Server;

$directory = "stylesheets";

Server::serveFrom($directory);
{% endhighlight %}

Create the directory set in the script alongside the script, then add your
`scss` files to it.

If we've got a file in there called `style.scss`, then we just need to hit the
url: `example.com/style.php/style.scss` to get the compiled css.

If there is an error compiling, the url will result in a `500` error with the
error message. If the file can't be found, then a friendly `404` is returned.

**scssphp** will automatically create a `scss_cache` directory inside the
stylesheets directory where it will cache the compiled output. This way it can
quickly serve the files if no modifications have been made. Your PHP script
must have permission to write in `scss_cache`.

### Compiler Interface

If you're interested in directly using the compiler, then all you need to either
require `scss.inc.php` or use your `composer` generated auto-loader, and then
invoke the `Compiler` class:

{% highlight php startinline=true %}
require_once "scssphp/scss.inc.php";

use Leafo\ScssPhp\Compiler;

$scss = new Compiler();

echo $scss->compile('
  $color: #abc;
  div { color: lighten($color, 20%); }
');
{% endhighlight %}

The `compile` method takes `SCSS` as a string, and returns the `CSS`. If there
is an error when compiling then an exception is thrown with an appropriate
message.


For a more detailed guide consult <a href="{{ site.baseurl }}docs/">{{ site.baseurl }}docs/</a>.

<a name="issues"></a>

## Issues

Find any issues? I'd love to fix them for you, post about them on [the issues tracker][3].

<div id="changelog"></div>

## Changelog

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
  * wrote <a href="{{ site.baseurl }}docs/">documentation</a>
* **0.0.1** -- July 29th, 2012 -- Initial Release

<script type="text/javascript">
(function() {
  var changelog = jQuery("#changelog").nextAll("ul:first");
  var hidden = changelog.children("li").slice(1).hide();
  if (hidden.length) {
    var show_all = jQuery("<a href=''>Show All</a>").insertAfter(changelog).on("click", function() {
      hidden.show();
      show_all.remove();
      return false;
    });
  }
})();
</script>

  [0]: http://sass-lang.com/
  [1]: http://sass-lang.com/documentation/file.SASS_REFERENCE.html
  [2]: http://packagist.org/
  [3]: {{ site.repo_url }}/issues
  [4]: {{ site.repo_url }}/
