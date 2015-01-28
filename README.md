# scssphp v0.1.1
### <http://leafo.net/scssphp>

[![Build Status](https://secure.travis-ci.org/leafo/scssphp.png)](http://travis-ci.org/leafo/scssphp)

`scssphp` is a compiler for SCSS written in PHP.

It implements SCSS 3.2.12. It does not implement the SASS syntax, only the SCSS
syntax.

Checkout the homepage, <http://leafo.net/scssphp>, for directions on how to use.

## Running Tests

`scssphp` uses [PHPUnit](https://github.com/sebastianbergmann/phpunit) for testing.

Run the following command from the root directory to run every test:

    phpunit tests

There are two kinds of tests in the `tests/` directory:

* `ApiTest.php` contains various unit tests that test the PHP interface.
* `ExceptionTest.php` contains unit tests that test for exceptions thrown by the parser and compiler.
* `InputTest.php` compiles every `.scss` file in the `tests/inputs` directory
  then compares to the respective `.css` file in the `tests/outputs` directory.

When changing any of the tests in `tests/inputs`, the tests will most likely
fail because the output has changed. Once you verify that the output is correct
you can run the following command to rebuild all the tests:

    BUILD=true phpunit tests

This will compile all the tests, and save results into `tests/outputs`.

## (optional) Output SCSS line numbers

Now you can output the original SCSS line numbers within the compiled CSS file for better frontend debugging.

Works great in combination with frontend debugging tools like https://addons.mozilla.org/de/firefox/addon/firecompass-for-firebug/

To activate this feature you need to call `->setLineNumbers(true)` after creating a new instance of class 'compiler'.

code sample:

    namespace Leafo\ScssPhp;

    use Leafo\ScssPhp\Server;
    use \Leafo\ScssPhp\Compiler;

    require "lib/scssphp/scss.inc.php";
    
    $directory = "css";

    $scss = new Compiler();
    $scss->setLineNumbers(true);

    $server = new Server($directory, null, $scss);
    $server->serve();


You can also call the 'compile' method directly (without using an instance of 'server' like above) 
    
    namespace Leafo\ScssPhp;
    
    use Leafo\ScssPhp\Server;
    use \Leafo\ScssPhp\Compiler;
    
    require "lib/scssphp/scss.inc.php";
    
    $scss = new Compiler();
    
    //the name argument is optional
    $scss->setLineNumbers(true,'anyname.scss');
    
    echo $scss->compile('
      $color: #abc;
      div { color: lighten($color, 20%); }
    ');


Performance impact is around 10% when a new CSS file is compiled with line numbers, compared to the same file without line numbers.

**important note:** this feature has only been tested with the standard formatter ('Leafo\ScssPhp\Formatter\Nested'). 
Using formatters like "compact" will remove line breaks and frontend debugging tools might have trouble to output the corresponding line from the comment.

