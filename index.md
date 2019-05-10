---
layout: default
---

**scssphp** is a compiler for [SCSS][0] 3.x written in PHP.

SCSS is a CSS preprocessor language that adds many features like variables,
mixins, imports, nesting, color manipulation, functions, and control directives.

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

**scssphp** requires PHP version 5.6 (or above).

## Language Reference

For a complete guide to the syntax of SCSS, consult the [official documentation][1].

## Command Line Tool

A really basic command line tool is included for integration with scripts. It
is called `pscss`. It reads SCSS from either a named input file or standard in,
and returns the CSS to standard out.

Usage: bin/pscss [options] [input-file]

### Options

If passed the flag `-h` (or `--help`), input is ignored and a summary of the command's usage is returned.

If passed the flag `-v` (or `--version`), input is ignored and the current version is returned.

If passed the flag `-T`, a formatted parse tree is returned instead of the compiled CSS.

The flag `-f` (or `--style`) can be used to set the [formatter](#output-formatting):

{% highlight bash %}
$ bin/pscss -f compressed < styles.scss
{% endhighlight %}

The flag `-i` (or `--load_paths`) can be used to set import paths for the loader. On Unix/Linux systems,
the paths are colon separated.

The flag `-p` (or `--precision`) can be used to set the decimal number precision. The default is 5.

The flag `--debug-info` can be used to annotate the selectors with CSS {% raw %}@{% endraw %}media queries that identify the source file and line number.

The flag `--line-comments` (or `--line-numbers`) can be used to annotate the selectors with comments that identify the source file and line number.

## SCSSPHP Library Reference

Complete documentation for **scssphp** is located at <a href="{{ site.baseurl }}/docs/">{{ site.baseurl }}/docs/</a>.

To use the scssphp library either require `scss.inc.php` or use your `composer` generated auto-loader, and then
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

For a more detailed guide, consult <a href="{{ site.baseurl }}/docs/">{{ site.baseurl }}/docs/</a>.

<a name="issues"></a>

## Issues

Please submit bug reports and feature requests to the [the issue tracker][3]. Pull requests also welcome.

## Changelog

For a list of **scssphp** changes, refer to <a href="{{ site.baseurl }}/docs/changelog.html">{{ site.baseurl }}/docs/changelog.html</a>.

  [0]: http://sass-lang.com/
  [1]: http://sass-lang.com/documentation/file.SASS_REFERENCE.html
  [2]: http://packagist.org/
  [3]: {{ site.repo_url }}/issues
