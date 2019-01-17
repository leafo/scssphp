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
 * Failing tests
 *
 * {@internal
 *     Minimal tests as reported in github issues.
 * }}
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class FailingTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->scss = new Compiler();
    }

    /**
     * @param string $id
     * @param string $scss
     * @param string $expected
     *
     * @dataProvider provideFailing
     */
    public function testFailing($id, $scss, $expected)
    {
        static $init = false;

        if (! getenv('TEST_SCSS_COMPAT')) {
            if (! $init) {
                $init = true;

                $this->markTestSkipped('Define TEST_SCSS_COMPAT=1 to enable ruby scss compatibility tests');
            }

            return;
        }

        $output = $this->compile($scss);

        $this->assertEquals(rtrim($expected), rtrim($output), $id);
    }

    /**
     * @return array
     */
    public function provideFailing()
    {
        // @codingStandardsIgnoreStart
        return array(
            array(
                '#67 - weird @extend behavior', <<<'END_OF_SCSS'
.nav-bar {
    background: #eee;
    > .item {
        margin: 0 10px;
    }
}


header ul {
    @extend .nav-bar;
    > li {
        @extend .item;
    }
}
END_OF_SCSS
                , <<<END_OF_EXPECTED
.nav-bar, header ul {
  background: #eee; }
  .nav-bar > .item, header ul > .item, header ul > li {
    margin: 0 10px; }
END_OF_EXPECTED
            ),
            array(
                '#368 - self in selector', <<<'END_OF_SCSS'
.test {
    &:last-child:not(+ &:first-child) {
      padding-left: 10px;
    }
}
END_OF_SCSS
                , <<<END_OF_EXPECTED
.test:last-child:not(+ .test:first-child) {
  padding-left: 10px; }
END_OF_EXPECTED
            ),
            array(
                '#371 - nested self selector', <<<'END_OF_SCSS'
ul, ol {
    & & {
      display: block;
    }
  }
END_OF_SCSS
                , <<<END_OF_EXPECTED
ul ul, ol ul, ul ol, ol ol {
  display: block; }
END_OF_EXPECTED
            ),
/*************************************************************
            array(
                '', <<<'END_OF_SCSS'
END_OF_SCSS
                , <<<END_OF_EXPECTED
END_OF_EXPECTED
            ),
*************************************************************/
        );
        // @codingStandardsIgnoreEnd
    }

    private function compile($str)
    {
        return trim($this->scss->compile($str));
    }
}
