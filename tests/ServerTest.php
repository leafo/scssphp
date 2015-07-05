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

use Leafo\ScssPhp\Server;

/**
 * Server test
 *
 * @author Zimzat <zimzat@zimzat.com>
 */
class ServerTest extends \PHPUnit_Framework_TestCase
{
    public function testCheckedCachedCompile()
    {
        $server = new Server(__DIR__ . '/inputs/');
        $css = $server->checkedCachedCompile(__DIR__ . '/inputs/import.scss', '/tmp/scss.css');

        $this->assertFileExists('/tmp/scss.css');
        $this->assertFileExists('/tmp/scss.css.meta');
        $this->assertEquals($css, file_get_contents('/tmp/scss.css'));
        $this->assertNotNull(unserialize(file_get_contents('/tmp/scss.css.meta')));
    }
}
