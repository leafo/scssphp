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
 * SCSS block
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Block
{
    const T_MEDIA = 'media';
    const T_MIXIN = 'mixin';
    const T_INCLUDE = 'include';
    const T_FUNCTION = 'function';
    const T_EACH = 'each';
    const T_WHILE = 'while';
    const T_FOR = 'for';
    const T_IF = 'if';
    const T_ELSE = 'else';
    const T_ELSEIF = 'elseif';
    const T_DIRECTIVE = 'directive';
    const T_NESTED_PROPERTY = 'nestedprop';
    const T_BLOCK = 'block';
    const T_ROOT = 'root';
    const T_NULL = null;
    const T_COMMENT = 'comment';

    /**
     * @var string
     */
    public $type;

    /**
     * @var \Leafo\ScssPhp\Block
     */
    public $parent;

    /**
     * @var integer
     */
    public $sourcePosition;

    /**
     * @var integer
     */
    public $sourceIndex;

    /**
     * @var array
     */
    public $selectors;

    /**
     * @var array
     */
    public $comments;

    /**
     * @var array
     */
    public $children;
}
