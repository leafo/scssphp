
## About

**scssphp** is a compiler for [SCSS][0] written in PHP.

The entire compiler comes in a single class file ready for including in any
kind of project in addition to a command line tool for running the compiler
from the terminal.

**scssphp** implements the latest version of SCSS (3.1.20). It doesnt not
implement the SASS syntax, only the SCSS syntax.

Follow the author on twitter: [@moonscript](http://twitter.com/moonscript).

<div class="github-buttons">
<iframe src="http://markdotto.github.com/github-buttons/github-btn.html?user=leafo&repo=lessphp&type=watch&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="110px" height="20px"></iframe>
<iframe src="http://markdotto.github.com/github-buttons/github-btn.html?user=leafo&repo=lessphp&type=fork&count=true" allowtransparency="true" frameborder="0" scrolling="0" width="95px" height="20px"></iframe>
</div>

<a name="installing"></a>
## Installing

You can always download the latest version here:  
<a href="$root/src/scssphp-$current_version.tar.gz">scssphp-$current_version.tar.gz</a>

You can also find the latest source online:  
<https://github.com/leafo/scssphp/>

If you use [Packagist][2] for installing packages, then you can update your `composer.json` like so:

    ```json
    {
      "require": {
        "leafo/scssphp": "dev-master"
      }
    }
    ```

<a name="reference"></a>
## Language Reference

For a complete guide to the syntax of SCSS, consult the [official documentation][1].

## PHP Reference

The entire library comes in a single file, `scss.inc.php`. Just `require` it
and you're ready to start compiling SCSS.

    ```php
    <?php
    require "scss.inc.php";
    $scss = new scss();

    echo $scss->compile('
      $color: #abc;
      div { color: lighten($color, 20%); }
    ');

    ```

<a name="issues"></a>
## Issues

Find any issues? I'd love to fix them for you, post about them on [the issues tracker][3].

## Changelog

  * Initial Release v0.0.1 (July 29th, 2012)

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
