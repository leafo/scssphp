<?php
/**
 * SCSSPHP
 *
 * @copyright 2018 Anthon Pang
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.github.io/scssphp
 */

namespace Leafo\ScssPhp\Tests;

use Leafo\ScssPhp\SourceMap\Base64VLQ;

/**
 * Base64VLQ encoder test
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Base64VLQTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test encode
     *
     * param string $expected
     * param string $value
     *
     * @dataProvider getEncode
     */
    public function testEncode($expected, $value)
    {
        $encoder = new Base64VLQ;

        $this->assertEquals($expected, $encoder->encode($value));
    }

    /**
     * Data provider for testEncode
     *
     * @return array
     */
    public static function getEncode()
    {
        return [
            ['A', 0],
            ['C', 1],
            ['D', -1],
            ['2H', 123],
            ['qxmvrH', 123456789],
            ['+/////D', 2147483647], // 2^31-1
        ];
    }
}
