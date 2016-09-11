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

class FrameworkTest extends \PHPUnit_Framework_TestCase
{
    protected static $frameworks = [
        [
            "frameworkVerion" => "twbs/bootstrap4.0.0-alpha.4",
            "inputdirectory" => "../vendor/twbs/bootstrap/scss/",
            "inputfiles" => "bootstrap.scss",
        ],
        [
            "frameworkVerion" => "zurb/foundation6.2",
            "inputdirectory" => "../vendor/zurb/foundation/assets/",
            "inputfiles" => "foundation.scss",
        ]
    ];

    private $saveDir;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->scss = new Compiler();
        $this->saveDir = getcwd();

        chdir(__DIR__);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        chdir($this->saveDir);
    }

    /**
     * @dataProvider frameworkProvider
     */
    public function testFramework($frameworkVerion, $inputdirectory, $inputfiles)
    {
        $this->scss->addImportPath($inputdirectory);

        $input = file_get_contents($inputdirectory.$inputfiles);
        
        //Test if no exeption are raised for the given framwork
        $e = null;
        try {
            $this->scss->compile($input, $inputfiles);
        } catch (Exception $e) {
            // test fail
        }

        $this->assertNull($e);
    }

    public function frameworkProvider(){
        return self::$frameworks;
    }
}