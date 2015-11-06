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

namespace Leafo\ScssPhp;

/**
 * SCSS node
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
abstract class Node
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var integer
     */
    public $sourcePosition;

    /**
     * @var integer
     */
    public $sourceIndex;
}
