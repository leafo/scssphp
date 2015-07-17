<?php
/**
 * SCSSPHP
 *
 * @copyright 2012-2015 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.github.io/scssphp
 */

namespace Leafo\ScssPhp\Tests;

use Leafo\ScssPhp\Compiler;

/**
 * Scss Test - extracts tests from https://github.com/sass/sass/blob/stable/test/sass/scss/scss_test.rb
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class ScssTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $name
     * @param string $scss
     * @param string $css
     * @param mixed  $style
     *
     * @dataProvider provideTests
     */
    public function testTests($name, $scss, $css, $style)
    {
        static $init = false;

        if (! getenv('TEST_SCSS_COMPAT')) {
            if (! $init) {
                $init = true;

                $this->markTestSkipped('Define TEST_SCSS_COMPAT=1 to enable ruby scss compatibility tests');
            }

            return;
        }

        $compiler = new Compiler();
        $compiler->setFormatter('Leafo\ScssPhp\Formatter\\' . ($style ? ucfirst($style) : 'Nested'));

        $actual = $compiler->compile($scss);

        $this->assertEquals(rtrim($css), rtrim($actual), $name);

        // TODO: need to fix this in the formatters
        //$this->assertEquals(trim($css), trim($actual), $name);
    }

    /**
     * @return array
     */
    public function provideTests()
    {
        $state = 0;
        $lines = file(__DIR__ . '/scss_test.rb');
        $tests = array();
        $skipped = array();
        $scss = array();
        $css = array();
        $style = false;

        for ($i = 0, $s = count($lines); $i < $s; $i++) {
            $line = trim($lines[$i]);

            switch ($state) {
                case 0:
                    // outside of function
                    if (preg_match('/^\s*def test_([a-z_]+)/', $line, $matches)) {
                        $state = 1; // enter function
                        $name = $matches[1];
                        continue;
                    }

                    break;

                case 1:
                    // inside function
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }

                    if (preg_match('/= <<([A-Z_]+)\s*$/', $line, $matches)
                        || preg_match('/= render <<([A-Z_]+)\s*$/', $line, $matches)
                    ) {
                        $terminator = $matches[1];

                        for ($i++; trim($lines[$i]) !== $terminator; $i++) {
                            ;
                        }

                        continue;
                    }

                    if (preg_match('/^\s*assert_equal\(<<CSS, render\(<<SASS\)\)\s*$/', $line, $matches)
                        || preg_match('/^\s*assert_equal <<CSS, render\(<<SASS\)\s*$/', $line, $matches)
                    ) {
                        $state = 6; // sass parameter list
                        continue;
                    }

                    if (preg_match('/^\s*assert_equal\(<<CSS, render\(<<SCSS\)\)\s*$/', $line, $matches)
                        || preg_match('/^\s*assert_equal <<CSS, render\(<<SCSS\)\s*$/', $line, $matches)
                        // @codingStandardsIgnoreStart
                        || preg_match('/^\s*assert_equal\(<<CSS, render\(<<SCSS, :style => :(compressed|nested)\)\)\s*$/', $line, $matches)
                        || preg_match('/^\s*assert_equal <<CSS, render\(<<SCSS, :style => :(compressed|nested)\)\s*$/', $line, $matches)
                        // @codingStandardsIgnoreEnd
                    ) {
                        $state = 2; // get css
                        $style = isset($matches[1]) ? $matches[1] : null;
                        continue;
                    }

                    if (preg_match('/^\s*assert_warning .* do$/', $line)) {
                        $state = 4; // skip block
                        continue;
                    }

                    if (preg_match('/^\s*assert_raise_message.*render\(<<SCSS\)}\s*$/', $line)
                        || preg_match('/^\s*assert_raise_message.*render <<SCSS}\s*$/', $line)
                        || preg_match('/^\s*assert_raise_line.*render\(<<SCSS\)}\s*$/', $line)
                        || preg_match('/^\s*silence_warnings .*render\(<<SCSS\)}\s*$/', $line)
                        || preg_match('/^\s*assert_warning.*render <<SCSS}\s*$/', $line)
                        || preg_match('/^\s*assert_warning.*render\(<<SCSS\)}\s*$/', $line)
                        || preg_match('/^\s*assert_warning.*render\(<<SCSS\)\)}\s*$/', $line)
                        || preg_match('/^\s*assert_no_warning.*render\(<<SCSS\)\)}\s*$/', $line)
                        || preg_match('/^\s*assert_no_warning.*render\(<<SCSS\)}\s*$/', $line)
                        || preg_match('/^\s*render\(<<SCSS\)\s*$/', $line)
                        || preg_match('/^\s*render <<SCSS\s*$/', $line)
                    ) {
                        $state = 6; // begin parameter list
                        continue;
                    }

                    if (preg_match('/^\s*assert_equal\(<<CSS,/', $line)) {
                        for ($i++; trim($lines[$i]) !== 'CSS'; $i++) {
                            ;
                        }

                        continue;
                    }

                    if (preg_match('/^\s*assert_equal[ (].*,$/', $line)
                    ) {
                        $i++; // throw-away the next line too
                        continue;
                    }

                    if (preg_match('/^\s*assert_equal[ (]/', $line)
                        || preg_match('/^\s*assert_parses/', $line)
                        || preg_match('/^\s*assert\(/', $line)
                        || preg_match('/^\s*render[ (]"/', $line)
                        || $line === 'rescue Sass::SyntaxError => e'
                    ) {
                        continue;
                    }

                    if (preg_match('/^\s*end\s*$/', $line)) {
                        $state = 0; // exit function

                        $tests[] = array($name, implode($scss), implode($css), $style);
                        $scss = array();
                        $css = array();
                        $style = null;
                        continue;
                    }

                    $skipped[] = $line;

                    break;

                case 2:
                    // get css
                    if (preg_match('/^CSS\s*$/', $line)) {
                        $state = 3; // get scss
                        continue;
                    }
   
                    $css[] = $lines[$i];

                    break;

                case 3:
                    // get scss
                    if (preg_match('/^SCSS\s*$/', $line)) {
                        $state = 1; // end of parameter list
                        continue;
                    }
   
                    $scss[] = $lines[$i];

                    break;

                case 4:
                    // inside block
                    if (preg_match('/^\s*end\s*$/', $line)) {
                        $state = 1; // end block
                        continue;
                    }

                    if (preg_match('/^\s*assert_equal <<CSS, render\(<<SCSS\)\s*$/', $line)) {
                        $state = 5; // begin parameter list
                        continue;
                    }

                    break;

                case 5:
                    // consume parameters
                    if (preg_match('/^SCSS\s*$/', $line)) {
                        $state = 4; // end of parameter list
                        continue;
                    }
   
                    break;

                case 6:
                    // consume parameters
                    if (preg_match('/^S[AC]SS\s*$/', $line)) {
                        $state = 1; // end of parameter list
                        continue;
                    }
   
                    break;
            }
        }

        // var_dump($skipped);

        return $tests;
    }
}
