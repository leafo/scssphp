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
    protected function blockLines($block)
    {
        if (empty($block->lines)) {
            echo "block->lines: []\n";

            return;
        }

        foreach ($block->lines as $index => $line) {
            echo "block->lines[{$index}]: $line\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function blockSelectors($block)
    {
        if (empty($block->selectors)) {
            echo "block->selectors: []\n";

            return;
        }

        foreach ($block->selectors as $index => $selector) {
            echo "block->selectors[{$index}]: $selector\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function blockChildren($block)
    {
        if (empty($block->children)) {
            echo "block->children: []\n";

            return;
        }

        foreach ($block->children as $i => $child) {
            $this->block($child);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function block($block)
    {
        echo "block->type: {$block->type}\n" .
             "block->depth: {$block->depth}\n";

        $this->blockSelectors($block);
        $this->blockLines($block);
        $this->blockChildren($block);
    }
}
