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

namespace Leafo\ScssPhp\Node;

use Leafo\ScssPhp\Node;
use Leafo\ScssPhp\Type;

/**
 * SCSS dimension + optional units
 *
 * {@internal
 *     This is a work-in-progress.
 *
 *     The \ArrayAccess interface is temporary until the migration is complete.
 * }}
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Number extends Node implements \ArrayAccess
{
    /**
     * @var integer
     */
    static public $precision = 5;

    /**
     * @see http://www.w3.org/TR/2012/WD-css3-values-20120308/
     *
     * @var array
     */
    static protected $unitTable = array(
        'in' => array(
            'in' => 1,
            'pc' => 6,
            'pt' => 72,
            'px' => 96,
            'cm' => 2.54,
            'mm' => 25.4,
            'q'  => 101.6,
        ),
        'turn' => array(
            'deg' => 180,
            'grad' => 200,
            'rad' => M_PI,
            'turn' => 0.5,
        ),
        's' => array(
            's' => 1,
            'ms' => 1000,
        ),
        'Hz' => array(
            'Hz' => 1,
            'kHz' => 0.001,
        ),
        'dpi' => array(
            'dpi' => 1,
            'dpcm' => 2.54,
            'dppx' => 96,
        ),
    );

    /**
     * @var integer|float
     */
    public $dimension;

    /**
     * @var string
     */
    public $units;

    /**
     * Initialize number
     *
     * @param mixed  $dimension
     * @param string $initialUnit
     */
    public function __construct($dimension, $initialUnit)
    {
        $this->type      = Type::T_NUMBER;
        $this->dimension = $dimension;
        $this->units     = $initialUnit;
    }

    /**
     * Coerce number to target units
     *
     * @param array $units
     *
     * @return \Leafo\ScssPhp\Node\Number
     */
    public function coerce($units)
    {
        $value = $this->dimension;

        if (isset(self::$unitTable[$this->units][$units])) {
            $value *= self::$unitTable[$this->units][$units];
        }

        return new Number($value, $units);
    }

    /**
     * Normalize number
     *
     * @return \Leafo\ScssPhp\Node\Number
     */
    public function normalize()
    {
        if (isset(self::$unitTable['in'][$this->units])) {
            $conv = self::$unitTable['in'][$this->units];

            return new Number($this->dimension / $conv, 'in');
        }

        return new Number($this->dimension, $this->units);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        if ($offset === -2) {
            return $sourceIndex !== null;
        }

        if ($offset === -1
            || $offset === 0
            || $offset === 1
            || $offset === 2
        ) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        switch ($offset) {
            case -2:
                return $this->sourceIndex;

            case -1:
                return $this->sourcePosition;

            case 0:
                return $this->type;

            case 1:
                return $this->dimension;

            case 2:
                return $this->units;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === 1) {
            $this->dimension = $value;
        } elseif ($offset === 2) {
            $this->units = $value;
        } elseif ($offset == -1) {
            $this->sourcePosition = $value;
        } elseif ($offset == -2) {
            $this->sourceIndex = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        if ($offset === 1) {
            $this->dimension = null;
        } elseif ($offset === 2) {
            $this->units = null;
        } elseif ($offset === -1) {
            $this->sourcePosition = null;
        } elseif ($offset === -2) {
            $this->sourceIndex = null;
        }
    }

    /**
     * Returns true if the number is unitless
     *
     * @return boolean
     */
    public function unitless()
    {
        return empty($this->units);
    }

    /**
     * Returns unit(s) as the product of numerator units divided by the product of denominator units
     *
     * @return string
     */
    public function unitStr()
    {
        return $this->units;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $value = round($this->dimension, self::$precision);

        if (empty($this->units)) {
            return (string) $value;
        }

        return (string) $value . $this->units;
    }
}
