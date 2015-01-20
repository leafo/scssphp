**scssphp** is a compiler for [SCSS][0] written in PHP.

SCSS is a CSS preprocessor that adds many features like variables, mixins,
imports, color manipulation, functions, and tons of other powerful features.

The entire compiler comes in a single class file ready for including in any
kind of project in addition to a command line tool for running the compiler
from the terminal.

**scssphp** implements SCSS (3.2.12). It does not implement the SASS syntax,
only the SCSS syntax.

Follow the author on twitter: [@moonscript](http://twitter.com/moonscript).

<div class="github-buttons">
<iframe src="http://ghbtns.com/github-btn.html?user=leafo&repo=scssphp&type=watch&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="110px" height="20px"></iframe>
<iframe src="http://ghbtns.com/github-btn.html?user=leafo&repo=scssphp&type=fork&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="95px" height="20px"></iframe>
</div>

<a name="installing"></a>
## Installing

You can always download the latest version here:
<a href="$root/src/scssphp-$current_version.tar.gz" id="download-link">scssphp-$current_version.tar.gz</a>

You can also find the latest source online:
<https://github.com/leafo/scssphp/>

If you use [Packagist][2] for installing packages, then you can update your `composer.json` like so:

$render{[[composer]]}

<a name="quickstart"></a>
## Language Reference

For a complete guide to the syntax of SCSS, consult the [official documentation][1].

## PHP Reference

Complete documentation for **scssphp** is located at <a href="$root/docs/">http://leafo.net/scssphp/docs/</a>.

### Quickstart

If you just want to start serving compiled `scss` files as quick as possible
then start here.

**scssphp** comes with a easy to use class that automatically compiles modified
`scss` files and serves them from a directory you specify.

Create a file, like `style.php`:

    ```php
    <?php
    $directory = "stylesheets";

    require_once "scssphp/scss.inc.php";

    use Leafo\ScssPhp\Server;

    Server::serveFrom($directory);

    ```

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
require `scss.inc.php` or use your `composer` generated auto-laoder, and then
invoke the `Compiler` class:

    ```php
    <?php
    require_once "scssphp/scss.inc.php";

    use Leafo\ScssPhp\Compiler;

    $scss = new Compiler();

    echo $scss->compile('
      $color: #abc;
      div { color: lighten($color, 20%); }
    ');

    ```

The `compile` method takes `SCSS` as a string, and returns the `CSS`. If there
is an error when compiling then an exception is thrown with an appropriate
message.


For a more detailed guide consult <a href="$root/docs/">http://leafo.net/scssphp/docs/</a>.

<a name="issues"></a>
## Issues

Find any issues? I'd love to fix them for you, post about them on [the issues tracker][3].

<div id="changelog"></div>
## Changelog

* **0.1.1** -- Aug 12, 2014
  * add stub classes -- a backward compatibility layer (vladimmi)
* **0.1.0** -- Aug 9, 2014
  * raise PHP requirement (5.3+)
  * reformat/reorganize source files to be PSR-2 compliant
* **0.0.15** -- Aug 6, 2014
  * fix regression with default values in functions (torkiljohnsen)
* **0.0.14** -- Aug 5, 2014
  * @keyframes $name - didn't work inside mixin (sergeylukin)
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
  * support multi-line continuation character (backslash)per CSS2.1 and CSS3 spec (caiosm1005)
  * imported partials should not be compiled (squarestar)
  * add setVariables() and unsetVariable() to interface (leafo/lessphp)
  * micro-optimizing is_null() (Yahasana)
* **0.0.10** -- April 14, 2014
  * fix media query merging (timonbaetz)
  * inline if should treat null as false (wonderslug)
  * optimizing toHSL() (jfsullivan)
* **0.0.9** -- December 23, 2013
  * fix @for/@while inside @content block (sergeylukin)
  * fix functions in mixin_content (timonbaetz)
  * fix infinite loop when target extends itself (oscherler)
  * fix function arguments are lost inside of @content block
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
  * @if and @while directives should treat null like false.
  * Add pscss as bin in composer.json (Christian Lück).
  * Fix !default bug (James Shannon, Alberto Aldegheri).
  * Fix mixin content includes (James Shannon, Christian Brandt).
  * Fix passing of varargs to another mixin.
  * Fix interpolation bug in expToString() (Matti Jarvinen).
* **0.0.5** -- March 11, 2013
  * Better compile time errors
  * Fix top level properties inside of a nested `@media` (Anthon Pang)
  * Fix some issues with `@extends` (Anthon Pang)
  * Enhanced handling of `null` (Anthon Pang)
  * Helper functions shouldn't mix with css builtins (Anthon Pang)
  * Enhance selector parsing (Guilherme Blanco, Anthon Pang)
  * Add Placeholder selector support (Martin Hasoň)
  * Add variable argument support (Martin Hasoň)
  * Add zip, index, comparable functions (Martin Hasoň)
  * A bunch of parser and bug fixes
* **0.0.4** -- Nov 3nd, 2012
  * [Import path can be a function](docs/#import_paths) (Christian Lück).
  * Correctly parse media queries with more than one item (Christian Lück).
  * Add `ie_hex_str`, `abs`, `min`, `max` functions (Martin Hasoň)
  * Ignore expressions inside of `calc()` (Martin Hasoň)
  * Improve operator evaluation (Martin Hasoň)
  * Add [`@content`](http://sass-lang.com/docs/yardoc/file.SASS_REFERENCE.html#mixin-content) support.
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
  * wrote <a href="http://leafo.net/scssphp/docs/">documentation</a>
* Initial Release v0.0.1 -- July 29th, 2012

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
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

<a name="comments"></a>
## Comments

<div class="comments" id="disqus_thread"></div>
<script type="text/javascript">
  var disqus_shortname = 'leafo';
  var disqus_url = 'http://leafo.net/scssphp/';

  (function() {
    var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
    dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
  })();
</script>


  [0]: http://sass-lang.com/
  [1]: http://sass-lang.com/docs/yardoc/file.SASS_REFERENCE.html#css_extensions
  [2]: http://packagist.org/
  [3]: https://github.com/leafo/scssphp/issues
  [4]: https://github.com/leafo/scssphp/
