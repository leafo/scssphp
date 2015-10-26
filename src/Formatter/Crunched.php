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

namespace Leafo\ScssPhp\Formatter;

use Leafo\ScssPhp\Formatter;

/**
 * SCSS crunched formatter
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Crunched extends Formatter
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->indentLevel = 0;
        $this->indentChar = '  ';
        $this->break = '';
        $this->open = '{';
        $this->close = '}';
        $this->tagSeparator = ',';
        $this->assignSeparator = ':';
    }

    /**
     * {@inheritdoc}
     */
    public function stripSemicolon(&$lines)
    {
        if (($count = count($lines))
            && substr($lines[$count - 1], -1) === ';'
        ) {
            $lines[$count - 1] = substr($lines[$count - 1], 0, -1);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function blockLines($block)
    {
        $inner = $this->indentStr();

        $glue = $this->break . $inner;

        foreach ($block->lines as $index => $line) {
            if (substr($line, 0, 2) === '/*') {
                unset($block->lines[$index]);
            }
        }

        echo $inner . implode($glue, $block->lines);

        if (! empty($block->children)) {
            echo $this->break;
        }
    }
}
