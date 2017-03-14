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

require_once __DIR__ . '/../example/Server.php';

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
        if (! file_exists(__DIR__ . '/inputs/scss_cache')) {
            mkdir(__DIR__ . '/inputs/scss_cache', 0755);
        }

        $server = new Server(__DIR__ . '/inputs/');
        $css = $server->checkedCachedCompile(__DIR__ . '/inputs/import.scss', '/tmp/scss.css');

        $this->assertFileExists('/tmp/scss.css');
        $this->assertFileExists('/tmp/scss.css.meta');
        $this->assertEquals($css, file_get_contents('/tmp/scss.css'));
        $this->assertNotNull(unserialize(file_get_contents('/tmp/scss.css.meta')));
    }
}
