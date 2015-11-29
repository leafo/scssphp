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
 * Exception test
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->scss = new Compiler();
    }

    /**
     * @param string $scss
     * @param string $expectedExceptionMessage
     *
     * @dataProvider provideScss
     */
    public function testThrowError($scss, $expectedExceptionMessage)
    {
        try {
            $this->compile($scss);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), $expectedExceptionMessage) === false) {
                $this->fail('Unexpected exception raised: ' . $e->getMessage() . ' vs ' . $expectedExceptionMessage);
            }

            return;
        }

        $this->fail('Expected exception to be raised: ' . $expectedExceptionMessage);
    }

    /**
     * @return array
     */
    public function provideScss()
    {
        return array(
            array(<<<'END_OF_SCSS'
.test {
  foo : bar;
END_OF_SCSS
                ,
                'unclosed block'
            ),
            array(<<<'END_OF_SCSS'
.test {
}}
END_OF_SCSS
                ,
                'unexpected }'
            ),
            array(<<<'END_OF_SCSS'
.test { color: #fff / 0; }
END_OF_SCSS
                ,
                'color: Can\'t divide by zero'
            ),
            array(<<<'END_OF_SCSS'
.test {
  @include foo();
}
END_OF_SCSS
                ,
                'Undefined mixin foo'
            ),
            array(<<<'END_OF_SCSS'
@mixin do-nothing() {
}

.test {
  @include do-nothing($a: "hello");
}
END_OF_SCSS
                ,
                'Mixin or function doesn\'t have an argument named $a.'
            ),
            array(<<<'END_OF_SCSS'
div {
  color: darken(cobaltgreen, 10%);
}
END_OF_SCSS
                ,
                'expecting color'
            ),
            array(<<<'END_OF_SCSS'
BODY {
    DIV {
        $bg: red;
    }

    background: $bg;
}
END_OF_SCSS
                ,
                'Undefined variable $bg'
            ),
            array(<<<'END_OF_SCSS'
@mixin example {
    background: $bg;
}

P {
    $bg: red;

    @include example;
}
END_OF_SCSS
                ,
                'Undefined variable $bg'
            ),
            array(<<<'END_OF_SCSS'
div { bottom: (4/2px); }
END_OF_SCSS
                ,
                'isn\'t a valid CSS value'
            ),
        );
    }

    private function compile($str)
    {
        return trim($this->scss->compile($str));
    }
}
