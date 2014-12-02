<?php
/**
 * SCSSPHP
 *
 * Stub classes for backward compatibility
 *
 * @copyright 2012-2014 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/gpl-license GPL-3.0
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.net/scssphp
 */

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scssc') ) {
	class scssc extends \Leafo\ScssPhp\Compiler
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_parser') ) {
	class scss_parser extends \Leafo\ScssPhp\Parser
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_formatter') ) {
	class scss_formatter extends \Leafo\ScssPhp\Formatter\Expanded
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_formatter_nested') ) {
	class scss_formatter_nested extends \Leafo\ScssPhp\Formatter\Nested
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_formatter_compressed' ) ) {
	class scss_formatter_compressed extends \Leafo\ScssPhp\Formatter\Compressed
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_formatter_crunched') ) {
	class scss_formatter_crunched extends \Leafo\ScssPhp\Formatter\Crunched
	{
	}
}

/**
 * @deprecated since 0.1.0
 */
if ( ! class_exists( 'scss_server') ) {
	class scss_server extends \Leafo\ScssPhp\Server
	{
	}
}