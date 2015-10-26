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
 * SCSS debug formatter
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Debug extends Formatter
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->indentLevel = 0;
        $this->indentChar = '';
        $this->break = "\n";
        $this->open = ' {';
        $this->close = ' }';
        $this->tagSeparator = ', ';
        $this->assignSeparator = ': ';
    }

    /**
     * {@inheritdoc}
     */
    protected function indentStr()
    {
        return str_repeat('  ', $this->indentLevel);
    }

    /**
     * {@inheritdoc}
     */
    protected function blockLines($block)
    {
        $indent = $this->indentStr();

        if (empty($block->lines)) {
            echo "{$indent}block->lines: []\n";

            return;
        }

        foreach ($block->lines as $index => $line) {
            echo "{$indent}block->lines[{$index}]: $line\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function blockSelectors($block)
    {
        $indent = $this->indentStr();

        if (empty($block->selectors)) {
            echo "{$indent}block->selectors: []\n";

            return;
        }

        foreach ($block->selectors as $index => $selector) {
            echo "{$indent}block->selectors[{$index}]: $selector\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function blockChildren($block)
    {
        $indent = $this->indentStr();

        if (empty($block->children)) {
            echo "{$indent}block->children: []\n";

            return;
        }

        $this->indentLevel++;

        foreach ($block->children as $i => $child) {
            $this->block($child);
        }

        $this->indentLevel--;
    }

    /**
     * {@inheritdoc}
     */
    protected function block($block)
    {
        $indent = $this->indentStr();

        echo "{$indent}block->type: {$block->type}\n" .
             "{$indent}block->depth: {$block->depth}\n";

        $this->blockSelectors($block);
        $this->blockLines($block);
        $this->blockChildren($block);
    }
}
