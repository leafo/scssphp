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
 * API test
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class ApiTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->scss = new Compiler();
    }

    public function testUserFunction()
    {
        $this->scss->registerFunction('add-two', function ($args) {
            list($a, $b) = $args;
            return $a[1] + $b[1];
        });

        $this->assertEquals(
            'result: 30;',
            $this->compile('result: add-two(10, 20);')
        );
    }

    public function testUserFunctionNull()
    {
        $this->scss->registerFunction('get-null', function ($args) {
            return Compiler::$null;
        });

        $this->assertEquals(
            '',
            $this->compile('result: get-null();')
        );
    }

    public function testUserFunctionKwargs()
    {
        $this->scss->registerFunction(
            'divide',
            function ($args, $kwargs) {
                return $kwargs['dividend'][1] / $kwargs['divisor'][1];
            },
            array('dividend', 'divisor')
        );

        $this->assertEquals(
            'result: 15;',
            $this->compile('result: divide($divisor: 2, $dividend: 30);')
        );
    }

    public function testImportMissing()
    {
        $this->assertEquals(
            '@import "missing";',
            $this->compile('@import "missing";')
        );
    }

    public function testImportCustomCallback()
    {
        $this->scss->addImportPath(function ($path) {
            return __DIR__ . '/inputs/' . str_replace('.css', '.scss', $path);
        });

        $this->assertEquals(
            trim(file_get_contents(__DIR__ . '/outputs/variables.css')),
            $this->compile('@import "variables.css";')
        );
    }

    /**
     * @dataProvider provideSetVariables
     */
    public function testSetVariables($expected, $scss, $variables)
    {
        $this->scss->setVariables($variables);

        $this->assertEquals($expected, $this->compile($scss));
    }

    public function provideSetVariables()
    {
        return array(
            array(
                ".magic {\n  color: red;\n  width: 760px; }",
                '.magic { color: $color; width: $base - 200; }',
                array(
                    'color' => 'red',
                    'base'  => '960px',
                ),
            ),
            array(
                ".logo {\n  color: #808080; }",
                '.logo { color: desaturate($primary, 100%); }',
                array(
                    'primary' => '#ff0000',
                ),
            ),
        );
    }

    public function testCompileByteOrderMarker()
    {
        // test that BOM is stripped/ignored
        $this->assertEquals(
            '@import "main";',
            $this->compile("\xEF\xBB\xBF@import \"main\";")
        );
    }

    public function compile($str)
    {
        return trim($this->scss->compile($str));
    }
}
