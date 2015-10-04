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

use Leafo\ScssPhp\Base\Range;
use Leafo\ScssPhp\Colors;
use Leafo\ScssPhp\Parser;
use Leafo\ScssPhp\Util;

/**
 * The scss compiler and parser.
 *
 * Converting SCSS to CSS is a three stage process. The incoming file is parsed
 * by `Parser` into a syntax tree, then it is compiled into another tree
 * representing the CSS structure by `Compiler`. The CSS tree is fed into a
 * formatter, like `Formatter` which then outputs CSS as a string.
 *
 * During the first compile, all values are *reduced*, which means that their
 * types are brought to the lowest form before being dump as strings. This
 * handles math equations, variable dereferences, and the like.
 *
 * The `compile` function of `Compiler` is the entry point.
 *
 * In summary:
 *
 * The `Compiler` class creates an instance of the parser, feeds it SCSS code,
 * then transforms the resulting tree to a CSS tree. This class also holds the
 * evaluation context, such as all available mixins and variables at any given
 * time.
 *
 * The `Parser` class is only concerned with parsing its input.
 *
 * The `Formatter` takes a CSS tree, and dumps it to a formatted string,
 * handling things like indentation.
 */

/**
 * SCSS compiler
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class Compiler
{
    const LINE_COMMENTS = 1;
    const DEBUG_INFO    = 2;

    /**
     * @var array
     */
    static protected $operatorNames = array(
        '+' => 'add',
        '-' => 'sub',
        '*' => 'mul',
        '/' => 'div',
        '%' => 'mod',

        '==' => 'eq',
        '!=' => 'neq',
        '<' => 'lt',
        '>' => 'gt',

        '<=' => 'lte',
        '>=' => 'gte',
    );

    /**
     * @var array
     */
    static protected $namespaces = array(
        'special' => '%',
        'mixin' => '@',
        'function' => '^',
    );

    /**
     * @var array
     */
    static protected $unitTable = array(
        'in' => array(
            'in' => 1,
            'pt' => 72,
            'pc' => 6,
            'cm' => 2.54,
            'mm' => 25.4,
            'px' => 96,
            'q'  => 101.6,
        ),
    );

    static public $true = array('keyword', 'true');
    static public $false = array('keyword', 'false');
    static public $null = array('null');
    static public $defaultValue = array('keyword', '');
    static public $selfSelector = array('self');
    static public $emptyList = array('list', '', array());
    static public $emptyMap = array('map', array(), array());
    static public $emptyString = array('string', '"', array());

    protected $importPaths = array('');
    protected $importCache = array();
    protected $userFunctions = array();
    protected $registeredVars = array();

    protected $numberPrecision = 5;
    protected $lineNumberStyle = null;

    protected $formatter = 'Leafo\ScssPhp\Formatter\Nested';

    private $indentLevel;
    private $commentsSeen;
    private $extends;
    private $extendsMap;
    private $parsedFiles;
    private $env;
    private $scope;
    private $parser;
    private $sourcePos;
    private $sourceParser;
    private $storeEnv;
    private $charsetSeen;
    private $stderr;
    private $shouldEvaluate;

    /**
     * Compile scss
     *
     * @api
     *
     * @param string $code
     * @param string $path
     *
     * @return string
     */
    public function compile($code, $path = null)
    {
        $this->indentLevel  = -1;
        $this->commentsSeen = array();
        $this->extends      = array();
        $this->extendsMap   = array();
        $this->parsedFiles  = array();
        $this->env          = null;
        $this->scope        = null;

        $this->stderr = fopen('php://stderr', 'w');

        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, 'C');

        $this->parser = new Parser($path);

        $tree = $this->parser->parse($code);

        $this->formatter = new $this->formatter();

        $this->addParsedFile($path);

        $this->rootEnv = $this->pushEnv($tree);
        $this->injectVariables($this->registeredVars);
        $this->compileRoot($tree);
        $this->popEnv();

        $out = $this->formatter->format($this->scope);

        setlocale(LC_NUMERIC, $locale);

        return $out;
    }

    /**
     * Is self extend?
     *
     * @param array $target
     * @param array $origin
     *
     * @return boolean
     */
    protected function isSelfExtend($target, $origin)
    {
        foreach ($origin as $sel) {
            if (in_array($target, $sel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Push extends
     *
     * @param array $target
     * @param array $origin
     */
    protected function pushExtends($target, $origin)
    {
        if ($this->isSelfExtend($target, $origin)) {
            return;
        }

        $i = count($this->extends);
        $this->extends[] = array($target, $origin);

        foreach ($target as $part) {
            if (isset($this->extendsMap[$part])) {
                $this->extendsMap[$part][] = $i;
            } else {
                $this->extendsMap[$part] = array($i);
            }
        }
    }

    /**
     * Make output block
     *
     * @param string $type
     * @param array  $selectors
     *
     * @return \stdClass
     */
    protected function makeOutputBlock($type, $selectors = null)
    {
        $out = new \stdClass;
        $out->type = $type;
        $out->lines = array();
        $out->children = array();
        $out->parent = $this->scope;
        $out->selectors = $selectors;
        $out->depth = $this->env->depth;

        return $out;
    }

    /**
     * Compile root
     *
     * @param \stdClass $rootBlock
     */
    protected function compileRoot($rootBlock)
    {
        $this->scope = $this->makeOutputBlock('root');

        $this->compileChildren($rootBlock->children, $this->scope);
        $this->flattenSelectors($this->scope);
    }

    /**
     * Flatten selectors
     *
     * @param \stdClass $block
     * @parent string   $parentKey
     */
    protected function flattenSelectors($block, $parentKey = null)
    {
        if ($block->selectors) {
            $selectors = array();

            foreach ($block->selectors as $s) {
                $selectors[] = $s;

                if (! is_array($s)) {
                    continue;
                }

                // check extends
                if (! empty($this->extendsMap)) {
                    $this->matchExtends($s, $selectors);

                    // remove duplicates
                    array_walk($selectors, function (&$value) {
                        $value = json_encode($value);
                    });
                    $selectors = array_unique($selectors);
                    array_walk($selectors, function (&$value) {
                        $value = json_decode($value);
                    });
                }
            }

            $block->selectors = array();
            $placeholderSelector = false;

            foreach ($selectors as $selector) {
                if ($this->hasSelectorPlaceholder($selector)) {
                    $placeholderSelector = true;
                    continue;
                }

                $block->selectors[] = $this->compileSelector($selector);
            }

            if ($placeholderSelector && 0 === count($block->selectors) && null !== $parentKey) {
                unset($block->parent->children[$parentKey]);

                return;
            }
        }

        foreach ($block->children as $key => $child) {
            $this->flattenSelectors($child, $key);
        }
    }

    /**
     * Match extends
     *
     * @param array   $selector
     * @param array   $out
     * @param integer $from
     * @param boolean $initial
     */
    protected function matchExtends($selector, &$out, $from = 0, $initial = true)
    {
        foreach ($selector as $i => $part) {
            if ($i < $from) {
                continue;
            }

            if ($this->matchExtendsSingle($part, $origin)) {
                $before = array_slice($selector, 0, $i);
                $after = array_slice($selector, $i + 1);
                $s = count($before);

                foreach ($origin as $new) {
                    $k = 0;

                    // remove shared parts
                    if ($initial) {
                        while ($k < $s && isset($new[$k]) && $before[$k] === $new[$k]) {
                            $k++;
                        }
                    }

                    $result = array_merge(
                        $before,
                        $k > 0 ? array_slice($new, $k) : $new,
                        $after
                    );

                    if ($result === $selector) {
                        continue;
                    }

                    $out[] = $result;

                    // recursively check for more matches
                    $this->matchExtends($result, $out, $i, false);

                    // selector sequence merging
                    if (! empty($before) && count($new) > 1) {
                        $result2 = array_merge(
                            array_slice($new, 0, -1),
                            $k > 0 ? array_slice($before, $k) : $before,
                            array_slice($new, -1),
                            $after
                        );

                        $out[] = $result2;
                    }
                }
            }
        }
    }

    /**
     * Match extends single
     *
     * @param array $rawSingle
     * @param array $outOrigin
     *
     * @return boolean
     */
    protected function matchExtendsSingle($rawSingle, &$outOrigin)
    {
        $counts = array();
        $single = array();

        foreach ($rawSingle as $part) {
            // matches Number
            if (! is_string($part)) {
                return false;
            }

            if (! preg_match('/^[\[.:#%]/', $part) && count($single)) {
                $single[count($single) - 1] .= $part;
            } else {
                $single[] = $part;
            }
        }

        foreach ($single as $part) {
            if (isset($this->extendsMap[$part])) {
                foreach ($this->extendsMap[$part] as $idx) {
                    $counts[$idx] = isset($counts[$idx]) ? $counts[$idx] + 1 : 1;
                }
            }
        }

        $outOrigin = array();
        $found = false;

        foreach ($counts as $idx => $count) {
            list($target, $origin) = $this->extends[$idx];

            // check count
            if ($count !== count($target)) {
                continue;
            }

            $rem = array_diff($single, $target);

            foreach ($origin as $j => $new) {
                // prevent infinite loop when target extends itself
                if ($this->isSelfExtend($single, $origin)) {
                    return false;
                }

                $origin[$j][count($origin[$j]) - 1] = $this->combineSelectorSingle(end($new), $rem);
            }

            $outOrigin = array_merge($outOrigin, $origin);

            $found = true;
        }

        return $found;
    }

    /**
     * Combine selector single
     *
     * @param array $base
     * @param array $other
     *
     * @return array
     */
    protected function combineSelectorSingle($base, $other)
    {
        $tag = null;
        $out = array();

        foreach (array($base, $other) as $single) {
            foreach ($single as $part) {
                if (preg_match('/^[^\[.#:]/', $part)) {
                    $tag = $part;
                } else {
                    $out[] = $part;
                }
            }
        }

        if ($tag) {
            array_unshift($out, $tag);
        }

        return $out;
    }

    /**
     * Compile media
     *
     * @param \stdClass $media
     */
    protected function compileMedia($media)
    {
        $this->pushEnv($media);

        $mediaQuery = $this->compileMediaQuery($this->multiplyMedia($this->env));

        if (! empty($mediaQuery)) {
            $this->scope = $this->makeOutputBlock('media', array($mediaQuery));

            $parentScope = $this->mediaParent($this->scope);
            $parentScope->children[] = $this->scope;

            // top level properties in a media cause it to be wrapped
            $needsWrap = false;

            foreach ($media->children as $child) {
                $type = $child[0];

                if ($type !== 'block' && $type !== 'media' && $type !== 'directive' && $type !== 'import') {
                    $needsWrap = true;
                    break;
                }
            }

            if ($needsWrap) {
                $wrapped = (object) array(
                    'selectors' => array(),
                    'children' => $media->children,
                );

                $media->children = array(array('block', $wrapped));
            }

            $this->compileChildren($media->children, $this->scope);

            $this->scope = $this->scope->parent;
        }

        $this->popEnv();
    }

    /**
     * Media parent
     *
     * @param \stdClass $scope
     *
     * @return \stdClass
     */
    protected function mediaParent($scope)
    {
        while (! empty($scope->parent)) {
            if (! empty($scope->type) && $scope->type !== 'media') {
                break;
            }

            $scope = $scope->parent;
        }

        return $scope;
    }

    /**
     * Compile at-root
     *
     * @param \stdClass $block
     */
    protected function compileAtRoot($block)
    {
        $env = $this->pushEnv($block);

        $envs = $this->compactEnv($env);

        if (isset($block->with)) {
            // @todo move outside of nested directives, e.g., (without: all), (without: media supports), (with: rule)
        } else {
            // exclude selectors by default
            $this->env->parent = $this->rootEnv;
        }

        $this->scope = $this->makeOutputBlock('at-root');
        $this->scope->depth = 1;
        $this->scope->parent->children[] = $this->scope;

        // wrap inline selector
        if ($block->selector) {
            $wrapped = (object) array(
                'parent' => $block,
                'sourcePosition' => $block->sourcePosition,
                'sourceParser' => $block->sourceParser,
                'selectors' => $block->selector,
                'comments' => array(),
                'children' => $block->children,
            );

            $block->children = array(array('block', $wrapped));
        }

        $this->compileChildren($block->children, $this->scope);

        $this->scope = $this->scope->parent;
        $this->env   = $this->extractEnv($envs);

        $this->popEnv();
    }

    /**
     * Compile keyframe block
     *
     * @param \stdClass $block
     * @param array     $selectors
     */
    protected function compileKeyframeBlock($block, $selectors)
    {
        $env = $this->pushEnv($block);

        $envs = $this->compactEnv($env);

        $this->env = $this->extractEnv(array_filter($envs, function ($e) {
            return ! isset($e->block->selectors);
        }));

        $this->scope = $this->makeOutputBlock($block->type, $selectors);
        $this->scope->depth = 1;
        $this->scope->parent->children[] = $this->scope;

        $this->compileChildren($block->children, $this->scope);

        $this->scope = $this->scope->parent;
        $this->env   = $this->extractEnv($envs);

        $this->popEnv();
    }

    /**
     * Compile nested block
     *
     * @param \stdClass $block
     * @param array     $selectors
     */
    protected function compileNestedBlock($block, $selectors)
    {
        $this->pushEnv($block);

        $this->scope = $this->makeOutputBlock($block->type, $selectors);
        $this->scope->parent->children[] = $this->scope;

        $this->compileChildren($block->children, $this->scope);

        $this->scope = $this->scope->parent;

        $this->popEnv();
    }

    /**
     * Recursively compiles a block.
     *
     * A block is analogous to a CSS block in most cases. A single SCSS document
     * is encapsulated in a block when parsed, but it does not have parent tags
     * so all of its children appear on the root level when compiled.
     *
     * Blocks are made up of selectors and children.
     *
     * The children of a block are just all the blocks that are defined within.
     *
     * Compiling the block involves pushing a fresh environment on the stack,
     * and iterating through the props, compiling each one.
     *
     * @see Compiler::compileChild()
     *
     * @param \stdClass $block
     */
    protected function compileBlock($block)
    {
        $env = $this->pushEnv($block);
        $env->selectors = $this->evalSelectors($block->selectors);

        $out = $this->makeOutputBlock(null);

        if (isset($this->lineNumberStyle) && count($env->selectors) && count($block->children)) {
            $annotation = $this->makeOutputBlock('comment');
            $annotation->depth = 0;

            $file = $block->sourceParser->getSourceName();
            $line = $block->sourceParser->getLineNo($block->sourcePosition);

            switch ($this->lineNumberStyle) {
                case self::LINE_COMMENTS:
                    $annotation->lines[] = '/* line ' . $line . ', ' . $file . ' */';
                    break;

                case self::DEBUG_INFO:
                    $annotation->lines[] = '@media -sass-debug-info{filename{font-family:"' . $file
                                         . '"}line{font-family:' . $line . '}}';
                    break;
            }

            $this->scope->children[] = $annotation;
        }

        $this->scope->children[] = $out;

        if (count($block->children)) {
            $out->selectors = $this->multiplySelectors($env);

            $this->compileChildren($block->children, $out);
        }

        $this->formatter->stripSemicolon($out->lines);

        $this->popEnv();
    }

    /**
     * Compile root level comment
     *
     * @param array $block
     */
    protected function compileComment($block)
    {
        $out = $this->makeOutputBlock('comment');
        $out->lines[] = $block[1];
        $this->scope->children[] = $out;
    }

    /**
     * Evaluate selectors
     *
     * @param array $selectors
     *
     * @return array
     */
    protected function evalSelectors($selectors)
    {
        $this->shouldEvaluate = false;

        $selectors = array_map(array($this, 'evalSelector'), $selectors);

        // after evaluating interpolates, we might need a second pass
        if ($this->shouldEvaluate) {
            $buffer = $this->collapseSelectors($selectors);
            $parser = new Parser(__METHOD__, false);

            if ($parser->parseSelector($buffer, $newSelectors)) {
                $selectors = array_map(array($this, 'evalSelector'), $newSelectors);
            }
        }

        return $selectors;
    }

    /**
     * Evaluate selector
     *
     * @param array $selector
     *
     * @return array
     */
    protected function evalSelector($selector)
    {
        return array_map(array($this, 'evalSelectorPart'), $selector);
    }

    /**
     * Evaluate selector part; replaces all the interpolates, stripping quotes
     *
     * @param array $part
     *
     * @return array
     */
    protected function evalSelectorPart($part)
    {
        foreach ($part as &$p) {
            if (is_array($p) && ($p[0] === 'interpolate' || $p[0] === 'string')) {
                $p = $this->compileValue($p);

                // force re-evaluation
                if (strpos($p, '&') !== false || strpos($p, ',') !== false) {
                    $this->shouldEvaluate = true;
                }
            } elseif (is_string($p) && strlen($p) >= 2 &&
                ($first = $p[0]) && ($first === '"' || $first === "'") &&
                substr($p, -1) === $first
            ) {
                $p = substr($p, 1, -1);
            }
        }

        return $this->flattenSelectorSingle($part);
    }

    /**
     * Collapse selectors
     *
     * @param array $selectors
     *
     * @return string
     */
    protected function collapseSelectors($selectors)
    {
        $parts = array();

        foreach ($selectors as $selector) {
            $output = '';

            array_walk_recursive(
                $selector,
                function ($value, $key) use (&$output) {
                    $output .= $value;
                }
            );

            $parts[] = $output;
        }

        return implode(', ', $parts);
    }

    /**
     * Flatten selector single; joins together .classes and #ids
     *
     * @param array $single
     *
     * @return array
     */
    protected function flattenSelectorSingle($single)
    {
        $joined = array();

        foreach ($single as $part) {
            if (empty($joined) ||
                ! is_string($part) ||
                preg_match('/[\[.:#%]/', $part)
            ) {
                $joined[] = $part;
                continue;
            }

            if (is_array(end($joined))) {
                $joined[] = $part;
            } else {
                $joined[count($joined) - 1] .= $part;
            }
        }

        return $joined;
    }

    /**
     * Compile selector to string; self(&) should have been replaced by now
     *
     * @param array $selector
     *
     * @return string
     */
    protected function compileSelector($selector)
    {
        if (! is_array($selector)) {
            return $selector; // media and the like
        }

        return implode(
            ' ',
            array_map(
                array($this, 'compileSelectorPart'),
                $selector
            )
        );
    }

    /**
     * Compile selector part
     *
     * @param arary $piece
     *
     * @return string
     */
    protected function compileSelectorPart($piece)
    {
        foreach ($piece as &$p) {
            if (! is_array($p)) {
                continue;
            }

            switch ($p[0]) {
                case 'self':
                    $p = '&';
                    break;

                default:
                    $p = $this->compileValue($p);
                    break;
            }
        }

        return implode($piece);
    }

    /**
     * Has selector placeholder?
     *
     * @param array $selector
     *
     * @return boolean
     */
    protected function hasSelectorPlaceholder($selector)
    {
        if (! is_array($selector)) {
            return false;
        }

        foreach ($selector as $parts) {
            foreach ($parts as $part) {
                if ('%' === $part[0]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Compile children
     *
     * @param array     $stms
     * @param \stdClass $out
     *
     * @return array
     */
    protected function compileChildren($stms, $out)
    {
        foreach ($stms as $stm) {
            $ret = $this->compileChild($stm, $out);

            if (isset($ret)) {
                return $ret;
            }
        }
    }

    /**
     * Compile media query
     *
     * @param array $queryList
     *
     * @return string
     */
    protected function compileMediaQuery($queryList)
    {
        $out = '@media';
        $first = true;

        foreach ($queryList as $query) {
            $type = null;
            $parts = array();

            foreach ($query as $q) {
                switch ($q[0]) {
                    case 'mediaType':
                        if ($type) {
                            $type = $this->mergeMediaTypes(
                                $type,
                                array_map(array($this, 'compileValue'), array_slice($q, 1))
                            );

                            if (empty($type)) { // merge failed
                                return null;
                            }
                        } else {
                            $type = array_map(array($this, 'compileValue'), array_slice($q, 1));
                        }
                        break;

                    case 'mediaExp':
                        if (isset($q[2])) {
                            $parts[] = '('
                                . $this->compileValue($q[1])
                                . $this->formatter->assignSeparator
                                . $this->compileValue($q[2])
                                . ')';
                        } else {
                            $parts[] = '('
                                . $this->compileValue($q[1])
                                . ')';
                        }
                        break;
                }
            }

            if ($type) {
                array_unshift($parts, implode(' ', array_filter($type)));
            }

            if (! empty($parts)) {
                if ($first) {
                    $first = false;
                    $out .= ' ';
                } else {
                    $out .= $this->formatter->tagSeparator;
                }

                $out .= implode(' and ', $parts);
            }
        }

        return $out;
    }

    /**
     * Merge media types
     *
     * @param array $type1
     * @param array $type2
     *
     * @return array|null
     */
    protected function mergeMediaTypes($type1, $type2)
    {
        if (empty($type1)) {
            return $type2;
        }

        if (empty($type2)) {
            return $type1;
        }

        $m1 = '';
        $t1 = '';

        if (count($type1) > 1) {
            $m1= strtolower($type1[0]);
            $t1= strtolower($type1[1]);
        } else {
            $t1 = strtolower($type1[0]);
        }

        $m2 = '';
        $t2 = '';

        if (count($type2) > 1) {
            $m2 = strtolower($type2[0]);
            $t2 = strtolower($type2[1]);
        } else {
            $t2 = strtolower($type2[0]);
        }

        if (($m1 === 'not') ^ ($m2 === 'not')) {
            if ($t1 === $t2) {
                return null;
            }

            return array(
                $m1 === 'not' ? $m2 : $m1,
                $m1 === 'not' ? $t2 : $t1,
            );
        }

        if ($m1 === 'not' && $m2 === 'not') {
            // CSS has no way of representing "neither screen nor print"
            if ($t1 !== $t2) {
                return null;
            }

            return array('not', $t1);
        }

        if ($t1 !== $t2) {
            return null;
        }

        // t1 == t2, neither m1 nor m2 are "not"
        return array(empty($m1)? $m2 : $m1, $t1);
    }

    /**
     * Compile import; returns true if the value was something that could be imported
     *
     * @param array $rawPath
     * @param array $out
     *
     * @return boolean
     */
    protected function compileImport($rawPath, $out)
    {
        if ($rawPath[0] === 'string') {
            $path = $this->compileStringContent($rawPath);

            if ($path = $this->findImport($path)) {
                $this->importFile($path, $out);

                return true;
            }

            return false;
        }

        if ($rawPath[0] === 'list') {
            // handle a list of strings
            if (count($rawPath[2]) === 0) {
                return false;
            }

            foreach ($rawPath[2] as $path) {
                if ($path[0] !== 'string') {
                    return false;
                }
            }

            foreach ($rawPath[2] as $path) {
                $this->compileImport($path, $out);
            }

            return true;
        }

        return false;
    }

    /**
     * Compile child; returns a value to halt execution
     *
     * @param array     $child
     * @param \stdClass $out
     *
     * @return array
     */
    protected function compileChild($child, $out)
    {
        $this->sourcePos = isset($child[Parser::SOURCE_POSITION]) ? $child[Parser::SOURCE_POSITION] : -1;
        $this->sourceParser = isset($child[Parser::SOURCE_PARSER]) ? $child[Parser::SOURCE_PARSER] : $this->parser;

        switch ($child[0]) {
            case 'import':
                list(, $rawPath) = $child;

                $rawPath = $this->reduce($rawPath);

                if (! $this->compileImport($rawPath, $out)) {
                    $out->lines[] = '@import ' . $this->compileValue($rawPath) . ';';
                }
                break;

            case 'directive':
                list(, $directive) = $child;

                $s = '@' . $directive->name;

                if (! empty($directive->value)) {
                    $s .= ' ' . $this->compileValue($directive->value);
                }

                if ($directive->name === 'keyframes' || substr($directive->name, -10) === '-keyframes') {
                    $this->compileKeyframeBlock($directive, array($s));
                } else {
                    $this->compileNestedBlock($directive, array($s));
                }
                break;

            case 'at-root':
                $this->compileAtRoot($child[1]);
                break;

            case 'media':
                $this->compileMedia($child[1]);
                break;

            case 'block':
                $this->compileBlock($child[1]);
                break;

            case 'charset':
                if (! $this->charsetSeen) {
                    $this->charsetSeen = true;

                    $out->lines[] = '@charset ' . $this->compileValue($child[1]) . ';';
                }
                break;

            case 'assign':
                list(, $name, $value) = $child;

                if ($name[0] === 'var') {
                    $flag = isset($child[3]) ? $child[3] : null;
                    $isDefault = $flag === '!default';
                    $isGlobal = $flag === '!global';

                    if ($isGlobal) {
                        $this->set($name[1], $this->reduce($value), false, $this->rootEnv);
                        break;
                    }

                    $shouldSet = $isDefault &&
                        (($result = $this->get($name[1], false)) === null
                        || $result === self::$null);

                    if (! $isDefault || $shouldSet) {
                        $this->set($name[1], $this->reduce($value));
                    }
                    break;
                }

                $compiledName = $this->compileValue($name);

                // handle shorthand syntax: size / line-height
                if ($compiledName === 'font') {
                    if ($value[0] === 'exp' && $value[1] === '/') {
                        $value = $this->expToString($value);
                    } elseif ($value[0] === 'list') {
                        foreach ($value[2] as &$item) {
                            if ($item[0] === 'exp' && $item[1] === '/') {
                                $item = $this->expToString($item);
                            }
                        }
                    }
                }

                // if the value reduces to null from something else then
                // the property should be discarded
                if ($value[0] !== 'null') {
                    $value = $this->reduce($value);

                    if ($value[0] === 'null') {
                        break;
                    }
                }

                $compiledValue = $this->compileValue($value);

                $out->lines[] = $this->formatter->property(
                    $compiledName,
                    $compiledValue
                );
                break;

            case 'comment':
                if ($out->type === 'root') {
                    $this->compileComment($child);
                    break;
                }

                $out->lines[] = $child[1];
                break;

            case 'mixin':
            case 'function':
                list(, $block) = $child;

                $this->set(self::$namespaces[$block->type] . $block->name, $block);
                break;

            case 'extend':
                list(, $selectors) = $child;

                foreach ($selectors as $sel) {
                    $results = $this->evalSelectors(array($sel));

                    foreach ($results as $result) {
                        // only use the first one
                        $result = current($result);

                        $this->pushExtends($result, $out->selectors);
                    }
                }
                break;

            case 'if':
                list(, $if) = $child;

                if ($this->isTruthy($this->reduce($if->cond, true))) {
                    return $this->compileChildren($if->children, $out);
                }

                foreach ($if->cases as $case) {
                    if ($case->type === 'else' ||
                        $case->type === 'elseif' && $this->isTruthy($this->reduce($case->cond))
                    ) {
                        return $this->compileChildren($case->children, $out);
                    }
                }
                break;

            case 'return':
                return $this->reduce($child[1], true);

            case 'each':
                list(, $each) = $child;

                $list = $this->coerceList($this->reduce($each->list));

                $this->pushEnv();

                foreach ($list[2] as $item) {
                    if (count($each->vars) === 1) {
                        $this->set($each->vars[0], $item, true);
                    } else {
                        list(,, $values) = $this->coerceList($item);

                        foreach ($each->vars as $i => $var) {
                            $this->set($var, isset($values[$i]) ? $values[$i] : self::$null, true);
                        }
                    }

                    $ret = $this->compileChildren($each->children, $out);

                    if ($ret) {
                        $this->popEnv();

                        return $ret;
                    }
                }

                $this->popEnv();
                break;

            case 'while':
                list(, $while) = $child;

                while ($this->isTruthy($this->reduce($while->cond, true))) {
                    $ret = $this->compileChildren($while->children, $out);

                    if ($ret) {
                        return $ret;
                    }
                }
                break;

            case 'for':
                list(, $for) = $child;

                $start = $this->reduce($for->start, true);
                $start = $start[1];
                $end = $this->reduce($for->end, true);
                $end = $end[1];
                $d = $start < $end ? 1 : -1;

                while (true) {
                    if ((! $for->until && $start - $d == $end) ||
                        ($for->until && $start == $end)
                    ) {
                        break;
                    }

                    $this->set($for->var, array('number', $start, ''));
                    $start += $d;

                    $ret = $this->compileChildren($for->children, $out);

                    if ($ret) {
                        return $ret;
                    }
                }
                break;

            case 'nestedprop':
                list(, $prop) = $child;

                $prefixed = array();
                $prefix = $this->compileValue($prop->prefix) . '-';

                foreach ($prop->children as $child) {
                    if ($child[0] === 'assign') {
                        array_unshift($child[1][2], $prefix);
                    }

                    if ($child[0] === 'nestedprop') {
                        array_unshift($child[1]->prefix[2], $prefix);
                    }

                    $prefixed[] = $child;
                }

                $this->compileChildren($prefixed, $out);
                break;

            case 'include':
                // including a mixin
                list(, $name, $argValues, $content) = $child;

                $mixin = $this->get(self::$namespaces['mixin'] . $name, false);

                if (! $mixin) {
                    $this->throwError("Undefined mixin $name");
                }

                $callingScope = $this->env;

                // push scope, apply args
                $this->pushEnv();
                $this->env->depth--;

                if (isset($content)) {
                    $content->scope = $callingScope;

                    $this->setRaw(self::$namespaces['special'] . 'content', $content);
                }

                if (isset($mixin->args)) {
                    $this->applyArguments($mixin->args, $argValues);
                }

                $this->env->marker = 'mixin';

                foreach ($mixin->children as $child) {
                    $this->compileChild($child, $out);
                }

                $this->popEnv();
                break;

            case 'mixin_content':
                $content = $this->get(self::$namespaces['special'] . 'content', false);

                if (! $content) {
                    $this->throwError('Expected @content inside of mixin');
                }

                if (! isset($content->children)) {
                    break;
                }

                $this->storeEnv = $content->scope;

                foreach ($content->children as $child) {
                    $this->compileChild($child, $out);
                }

                $this->storeEnv = null;

                break;

            case 'debug':
                list(, $value) = $child;

                $line = $this->parser->getLineNo($this->sourcePos);
                $value = $this->compileValue($this->reduce($value, true));
                fwrite($this->stderr, "Line $line DEBUG: $value\n");
                break;

            case 'warn':
                list(, $value) = $child;

                $line = $this->parser->getLineNo($this->sourcePos);
                $value = $this->compileValue($this->reduce($value, true));
                echo "Line $line WARN: $value\n";
                break;

            case 'error':
                list(, $value) = $child;

                $line = $this->parser->getLineNo($this->sourcePos);
                $value = $this->compileValue($this->reduce($value, true));
                $this->throwError("Line $line ERROR: $value\n");
                break;

            default:
                $this->throwError("unknown child type: $child[0]");
        }
    }

    /**
     * Reduce expression to string
     *
     * @param array $exp
     *
     * @return array
     */
    protected function expToString($exp)
    {
        list(, $op, $left, $right, $inParens, $whiteLeft, $whiteRight) = $exp;

        $content = array($this->reduce($left));

        if ($whiteLeft) {
            $content[] = ' ';
        }

        $content[] = $op;

        if ($whiteRight) {
            $content[] = ' ';
        }

        $content[] = $this->reduce($right);

        return array('string', '', $content);
    }

    /**
     * Is truthy?
     *
     * @param array $value
     *
     * @return array
     */
    protected function isTruthy($value)
    {
        return $value !== self::$false && $value !== self::$null;
    }

    /**
     * Should $value cause its operand to eval
     *
     * @param array $value
     *
     * @return boolean
     */
    protected function shouldEval($value)
    {
        switch ($value[0]) {
            case 'exp':
                if ($value[1] === '/') {
                    return $this->shouldEval($value[2], $value[3]);
                }

                // fall-thru
            case 'var':
            case 'fncall':
                return true;
        }

        return false;
    }

    /**
     * Reduce value
     *
     * @param array   $value
     * @param boolean $inExp
     *
     * @return array
     */
    protected function reduce($value, $inExp = false)
    {
        list($type) = $value;

        switch ($type) {
            case 'exp':
                list(, $op, $left, $right, $inParens) = $value;

                $opName = isset(self::$operatorNames[$op]) ? self::$operatorNames[$op] : $op;
                $inExp = $inExp || $this->shouldEval($left) || $this->shouldEval($right);

                $left = $this->reduce($left, true);

                if ($op !== 'and' && $op !== 'or') {
                    $right = $this->reduce($right, true);
                }

                // special case: looks like css short-hand
                if ($opName === 'div' && ! $inParens && ! $inExp && isset($right[2]) && $right[2] !== '') {
                    return $this->expToString($value);
                }

                $left = $this->coerceForExpression($left);
                $right = $this->coerceForExpression($right);

                $ltype = $left[0];
                $rtype = $right[0];

                $ucOpName = ucfirst($opName);
                $ucLType  = ucfirst($ltype);
                $ucRType  = ucfirst($rtype);

                // this tries:
                // 1. op[op name][left type][right type]
                // 2. op[left type][right type] (passing the op as first arg
                // 3. op[op name]
                $fn = "op${ucOpName}${ucLType}${ucRType}";

                if (is_callable(array($this, $fn)) ||
                    (($fn = "op${ucLType}${ucRType}") &&
                        is_callable(array($this, $fn)) &&
                        $passOp = true) ||
                    (($fn = "op${ucOpName}") &&
                        is_callable(array($this, $fn)) &&
                        $genOp = true)
                ) {
                    $unitChange = false;

                    if (! isset($genOp) &&
                        $left[0] === 'number' && $right[0] === 'number'
                    ) {
                        if ($opName === 'mod' && $right[2] !== '') {
                            $this->throwError("Cannot modulo by a number with units: $right[1]$right[2].");
                        }

                        $unitChange = true;
                        $emptyUnit = $left[2] === '' || $right[2] === '';
                        $targetUnit = '' !== $left[2] ? $left[2] : $right[2];

                        if ($opName !== 'mul') {
                            $left[2] = '' !== $left[2] ? $left[2] : $targetUnit;
                            $right[2] = '' !== $right[2] ? $right[2] : $targetUnit;
                        }

                        if ($opName !== 'mod') {
                            $left = $this->normalizeNumber($left);
                            $right = $this->normalizeNumber($right);
                        }

                        if ($opName === 'div' && ! $emptyUnit && $left[2] === $right[2]) {
                            $targetUnit = '';
                        }

                        if ($opName === 'mul') {
                            $left[2] = '' !== $left[2] ? $left[2] : $right[2];
                            $right[2] = '' !== $right[2] ? $right[2] : $left[2];
                        } elseif ($opName === 'div' && $left[2] === $right[2]) {
                            $left[2] = '';
                            $right[2] = '';
                        }
                    }

                    $shouldEval = $inParens || $inExp;

                    if (isset($passOp)) {
                        $out = $this->$fn($op, $left, $right, $shouldEval);
                    } else {
                        $out = $this->$fn($left, $right, $shouldEval);
                    }

                    if (isset($out)) {
                        if ($unitChange && $out[0] === 'number') {
                            $out = $this->coerceUnit($out, $targetUnit);
                        }

                        return $out;
                    }
                }

                return $this->expToString($value);

            case 'unary':
                list(, $op, $exp, $inParens) = $value;

                $inExp = $inExp || $this->shouldEval($exp);
                $exp = $this->reduce($exp);

                if ($exp[0] === 'number') {
                    switch ($op) {
                        case '+':
                            return $exp;

                        case '-':
                            $exp[1] *= -1;

                            return $exp;
                    }
                }

                if ($op === 'not') {
                    if ($inExp || $inParens) {
                        if ($exp === self::$false) {
                            return self::$true;
                        }

                        return self::$false;
                    }

                    $op = $op . ' ';
                }

                return array('string', '', array($op, $exp));

            case 'var':
                list(, $name) = $value;

                return $this->reduce($this->get($name));

            case 'list':
                foreach ($value[2] as &$item) {
                    $item = $this->reduce($item);
                }

                return $value;

            case 'map':
                foreach ($value[1] as &$item) {
                    $item = $this->reduce($item);
                }

                foreach ($value[2] as &$item) {
                    $item = $this->reduce($item);
                }

                return $value;

            case 'string':
                foreach ($value[2] as &$item) {
                    if (is_array($item)) {
                        $item = $this->reduce($item);
                    }
                }

                return $value;

            case 'interpolate':
                $value[1] = $this->reduce($value[1]);

                return $value;

            case 'fncall':
                list(, $name, $argValues) = $value;

                // user defined function?
                $func = $this->get(self::$namespaces['function'] . $name, false);

                if ($func) {
                    $this->pushEnv();

                    // set the args
                    if (isset($func->args)) {
                        $this->applyArguments($func->args, $argValues);
                    }

                    // throw away lines and children
                    $tmp = (object) array(
                        'lines' => array(),
                        'children' => array(),
                    );

                    $ret = $this->compileChildren($func->children, $tmp);

                    $this->popEnv();

                    return ! isset($ret) ? self::$defaultValue : $ret;
                }

                // built in function
                if ($this->callBuiltin($name, $argValues, $returnValue)) {
                    return $returnValue;
                }

                // need to flatten the arguments into a list
                $listArgs = array();

                foreach ((array)$argValues as $arg) {
                    if (empty($arg[0])) {
                        $listArgs[] = $this->reduce($arg[1]);
                    }
                }

                return array('function', $name, array('list', ',', $listArgs));

            default:
                return $value;
        }
    }

    /**
     * Normalize name
     *
     * @param string $name
     *
     * @return string
     */
    protected function normalizeName($name)
    {
        return str_replace('-', '_', $name);
    }

    /**
     * Normalize value
     *
     * @param array $value
     *
     * @return array
     */
    public function normalizeValue($value)
    {
        $value = $this->coerceForExpression($this->reduce($value));
        list($type) = $value;

        switch ($type) {
            case 'list':
                $value = $this->extractInterpolation($value);

                if ($value[0] !== 'list') {
                    return array('keyword', $this->compileValue($value));
                }

                foreach ($value[2] as $key => $item) {
                    $value[2][$key] = $this->normalizeValue($item);
                }

                return $value;

            case 'string':
                return array($type, '"', $this->compileStringContent($value));

            case 'number':
                return $this->normalizeNumber($value);

            default:
                return $value;
        }
    }

    /**
     * Normalize number; just does physical lengths for now
     *
     * @param array $number
     *
     * @return array
     */
    protected function normalizeNumber($number)
    {
        list(, $value, $unit) = $number;

        if (isset(self::$unitTable['in'][$unit])) {
            $conv = self::$unitTable['in'][$unit];

            return array('number', $value / $conv, 'in');
        }

        return $number;
    }

    /**
     * Add numbers
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opAddNumberNumber($left, $right)
    {
        return array('number', $left[1] + $right[1], $left[2]);
    }

    /**
     * Multiply numbers
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opMulNumberNumber($left, $right)
    {
        return array('number', $left[1] * $right[1], $left[2]);
    }

    /**
     * Subtract numbers
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opSubNumberNumber($left, $right)
    {
        return array('number', $left[1] - $right[1], $left[2]);
    }

    /**
     * Divide numbers
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opDivNumberNumber($left, $right)
    {
        if ($right[1] == 0) {
            $this->throwError('Division by zero');
        }

        return array('number', $left[1] / $right[1], $left[2]);
    }

    /**
     * Mod numbers
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opModNumberNumber($left, $right)
    {
        return array('number', $left[1] % $right[1], $left[2]);
    }

    /**
     * Add strings
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opAdd($left, $right)
    {
        if ($strLeft = $this->coerceString($left)) {
            if ($right[0] === 'string') {
                $right[1] = '';
            }

            $strLeft[2][] = $right;

            return $strLeft;
        }

        if ($strRight = $this->coerceString($right)) {
            if ($left[0] === 'string') {
                $left[1] = '';
            }

            array_unshift($strRight[2], $left);

            return $strRight;
        }
    }

    /**
     * Boolean and
     *
     * @param array   $left
     * @param array   $right
     * @param boolean $shouldEval
     *
     * @return array
     */
    protected function opAnd($left, $right, $shouldEval)
    {
        if (! $shouldEval) {
            return;
        }

        if ($left !== self::$false) {
            return $this->reduce($right, true);
        }

        return $left;
    }

    /**
     * Boolean or
     *
     * @param array   $left
     * @param array   $right
     * @param boolean $shouldEval
     *
     * @return array
     */
    protected function opOr($left, $right, $shouldEval)
    {
        if (! $shouldEval) {
            return;
        }

        if ($left !== self::$false) {
            return $left;
        }

        return $this->reduce($right, true);
    }

    /**
     * Compare colors
     *
     * @param string $op
     * @param array  $left
     * @param array  $right
     *
     * @return array
     */
    protected function opColorColor($op, $left, $right)
    {
        $out = array('color');

        foreach (range(1, 3) as $i) {
            $lval = isset($left[$i]) ? $left[$i] : 0;
            $rval = isset($right[$i]) ? $right[$i] : 0;

            switch ($op) {
                case '+':
                    $out[] = $lval + $rval;
                    break;

                case '-':
                    $out[] = $lval - $rval;
                    break;

                case '*':
                    $out[] = $lval * $rval;
                    break;

                case '%':
                    $out[] = $lval % $rval;
                    break;

                case '/':
                    if ($rval == 0) {
                        $this->throwError("color: Can't divide by zero");
                    }

                    $out[] = (int) ($lval / $rval);
                    break;

                case '==':
                    return $this->opEq($left, $right);

                case '!=':
                    return $this->opNeq($left, $right);

                default:
                    $this->throwError("color: unknown op $op");
            }
        }

        if (isset($left[4])) {
            $out[4] = $left[4];
        } elseif (isset($right[4])) {
            $out[4] = $right[4];
        }

        return $this->fixColor($out);
    }

    /**
     * Compare color and number
     *
     * @param string $op
     * @param array  $left
     * @param array  $right
     *
     * @return array
     */
    protected function opColorNumber($op, $left, $right)
    {
        $value = $right[1];

        return $this->opColorColor(
            $op,
            $left,
            array('color', $value, $value, $value)
        );
    }

    /**
     * Compare number and color
     *
     * @param string $op
     * @param array  $left
     * @param array  $right
     *
     * @return array
     */
    protected function opNumberColor($op, $left, $right)
    {
        $value = $left[1];

        return $this->opColorColor(
            $op,
            array('color', $value, $value, $value),
            $right
        );
    }

    /**
     * Compare number1 == number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opEq($left, $right)
    {
        if (($lStr = $this->coerceString($left)) && ($rStr = $this->coerceString($right))) {
            $lStr[1] = '';
            $rStr[1] = '';

            $left = $this->compileValue($lStr);
            $right = $this->compileValue($rStr);
        }

        return $this->toBool($left === $right);
    }

    /**
     * Compare number1 != number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opNeq($left, $right)
    {
        if (($lStr = $this->coerceString($left)) && ($rStr = $this->coerceString($right))) {
            $lStr[1] = '';
            $rStr[1] = '';

            $left = $this->compileValue($lStr);
            $right = $this->compileValue($rStr);
        }

        return $this->toBool($left !== $right);
    }

    /**
     * Compare number1 >= number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opGteNumberNumber($left, $right)
    {
        return $this->toBool($left[1] >= $right[1]);
    }

    /**
     * Compare number1 > number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opGtNumberNumber($left, $right)
    {
        return $this->toBool($left[1] > $right[1]);
    }

    /**
     * Compare number1 <= number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opLteNumberNumber($left, $right)
    {
        return $this->toBool($left[1] <= $right[1]);
    }

    /**
     * Compare number1 < number2
     *
     * @param array $left
     * @param array $right
     *
     * @return array
     */
    protected function opLtNumberNumber($left, $right)
    {
        return $this->toBool($left[1] < $right[1]);
    }

    /**
     * Cast to boolean
     *
     * @api
     *
     * @param mixed $thing
     *
     * @return array
     */
    public function toBool($thing)
    {
        return $thing ? self::$true : self::$false;
    }

    /**
     * Compiles a primitive value into a CSS property value.
     *
     * Values in scssphp are typed by being wrapped in arrays, their format is
     * typically:
     *
     *     array(type, contents [, additional_contents]*)
     *
     * The input is expected to be reduced. This function will not work on
     * things like expressions and variables.
     *
     * @api
     *
     * @param array $value
     *
     * @return string
     */
    public function compileValue($value)
    {
        $value = $this->reduce($value);

        list($type) = $value;

        switch ($type) {
            case 'keyword':
                return $value[1];

            case 'color':
                // [1] - red component (either number for a %)
                // [2] - green component
                // [3] - blue component
                // [4] - optional alpha component
                list(, $r, $g, $b) = $value;

                $r = round($r);
                $g = round($g);
                $b = round($b);

                if (count($value) === 5 && $value[4] !== 1) { // rgba
                    return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $value[4] . ')';
                }

                $h = sprintf('#%02x%02x%02x', $r, $g, $b);

                // Converting hex color to short notation (e.g. #003399 to #039)
                if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
                    $h = '#' . $h[1] . $h[3] . $h[5];
                }

                return $h;

            case 'number':
                return round($value[1], $this->numberPrecision) . $value[2];

            case 'string':
                return $value[1] . $this->compileStringContent($value) . $value[1];

            case 'function':
                $args = ! empty($value[2]) ? $this->compileValue($value[2]) : '';

                return "$value[1]($args)";

            case 'list':
                $value = $this->extractInterpolation($value);

                if ($value[0] !== 'list') {
                    return $this->compileValue($value);
                }

                list(, $delim, $items) = $value;

                $filtered = array();

                foreach ($items as $item) {
                    if ($item[0] === 'null') {
                        continue;
                    }

                    $filtered[] = $this->compileValue($item);
                }

                return implode("$delim ", $filtered);

            case 'map':
                $keys = $value[1];
                $values = $value[2];
                $filtered = array();

                for ($i = 0, $s = count($keys); $i < $s; $i++) {
                    $filtered[$this->compileValue($keys[$i])] = $this->compileValue($values[$i]);
                }

                array_walk($filtered, function (&$value, $key) {
                    $value = $key . ': ' . $value;
                });

                return '(' . implode(', ', $filtered) . ')';

            case 'interpolated':
                // node created by extractInterpolation
                list(, $interpolate, $left, $right) = $value;
                list(,, $whiteLeft, $whiteRight) = $interpolate;

                $left = count($left[2]) > 0 ?
                    $this->compileValue($left) . $whiteLeft : '';

                $right = count($right[2]) > 0 ?
                    $whiteRight . $this->compileValue($right) : '';

                return $left . $this->compileValue($interpolate) . $right;

            case 'interpolate':
                // raw parse node
                list(, $exp) = $value;

                // strip quotes if it's a string
                $reduced = $this->reduce($exp);

                switch ($reduced[0]) {
                    case 'string':
                        $reduced = array('keyword', $this->compileStringContent($reduced));
                        break;

                    case 'null':
                        $reduced = array('keyword', '');
                }

                return $this->compileValue($reduced);

            case 'null':
                return 'null';

            default:
                $this->throwError("unknown value type: $type");
        }
    }

    /**
     * Flatten list
     *
     * @param array $list
     *
     * @return string
     */
    protected function flattenList($list)
    {
        return $this->compileValue($list);
    }

    /**
     * Compile string content
     *
     * @param array $string
     *
     * @return string
     */
    protected function compileStringContent($string)
    {
        $parts = array();

        foreach ($string[2] as $part) {
            if (is_array($part)) {
                $parts[] = $this->compileValue($part);
            } else {
                $parts[] = $part;
            }
        }

        return implode($parts);
    }

    /**
     * Extract interpolation; it doesn't need to be recursive, compileValue will handle that
     *
     * @param array $list
     *
     * @return array
     */
    protected function extractInterpolation($list)
    {
        $items = $list[2];

        foreach ($items as $i => $item) {
            if ($item[0] === 'interpolate') {
                $before = array('list', $list[1], array_slice($items, 0, $i));
                $after  = array('list', $list[1], array_slice($items, $i + 1));

                return array('interpolated', $item, $before, $after);
            }
        }

        return $list;
    }

    /**
     * Find the final set of selectors
     *
     * @param \stdClass $env
     *
     * @return array
     */
    protected function multiplySelectors($env)
    {
        $envs            = $this->compactEnv($env);
        $selectors       = array();
        $parentSelectors = array(array());

        while ($env = array_pop($envs)) {
            if (empty($env->selectors)) {
                continue;
            }

            $selectors = array();

            foreach ($env->selectors as $selector) {
                foreach ($parentSelectors as $parent) {
                    $selectors[] = $this->joinSelectors($parent, $selector);
                }
            }

            $parentSelectors = $selectors;
        }

        return $selectors;
    }

    /**
     * Join selectors; looks for & to replace, or append parent before child
     *
     * @param array $parent
     * @param array $child
     *
     * @return array
     */
    protected function joinSelectors($parent, $child)
    {
        $setSelf = false;
        $out = array();

        foreach ($child as $part) {
            $newPart = array();

            foreach ($part as $p) {
                if ($p === self::$selfSelector) {
                    $setSelf = true;

                    foreach ($parent as $i => $parentPart) {
                        if ($i > 0) {
                            $out[] = $newPart;
                            $newPart = array();
                        }

                        foreach ($parentPart as $pp) {
                            $newPart[] = $pp;
                        }
                    }
                } else {
                    $newPart[] = $p;
                }
            }

            $out[] = $newPart;
        }

        return $setSelf ? $out : array_merge($parent, $child);
    }

    /**
     * Multiply media
     *
     * @param \stdClass $env
     * @param array     $childQueries
     *
     * @return array
     */
    protected function multiplyMedia($env, $childQueries = null)
    {
        if (! isset($env) ||
            ! empty($env->block->type) && $env->block->type !== 'media'
        ) {
            return $childQueries;
        }

        // plain old block, skip
        if (empty($env->block->type)) {
            return $this->multiplyMedia($env->parent, $childQueries);
        }

        $parentQueries = $env->block->queryList;
        if ($childQueries === null) {
            $childQueries = $parentQueries;
        } else {
            $originalQueries = $childQueries;
            $childQueries = array();

            foreach ($parentQueries as $parentQuery) {
                foreach ($originalQueries as $childQuery) {
                    $childQueries []= array_merge($parentQuery, $childQuery);
                }
            }
        }

        return $this->multiplyMedia($env->parent, $childQueries);
    }

    /**
     * Convert env linked list to stack
     *
     * @param \stdClass $env
     *
     * @return array
     */
    private function compactEnv($env)
    {
        for ($envs = array(); $env; $env = $env->parent) {
            $envs[] = $env;
        }

        return $envs;
    }

    /**
     * Convert env stack to singly linked list
     *
     * @param array $envs
     *
     * @return \stdClass
     */
    private function extractEnv($envs)
    {
        for ($env = null; $e = array_pop($envs);) {
            $e->parent = $env;
            $env = $e;
        }

        return $env;
    }

    /**
     * Push environment
     *
     * @param \stdClass $block
     *
     * @return \stdClass
     */
    protected function pushEnv($block = null)
    {
        $env = new \stdClass;
        $env->parent = $this->env;
        $env->store = array();
        $env->block = $block;
        $env->depth = isset($this->env->depth) ? $this->env->depth + 1 : 0;

        $this->env = $env;

        return $env;
    }

    /**
     * Pop environment
     */
    protected function popEnv()
    {
        $env = $this->env;
        $this->env = $this->env->parent;

        return $env;
    }

    /**
     * Get store environment
     *
     * @return \stdClass
     */
    protected function getStoreEnv()
    {
        return isset($this->storeEnv) ? $this->storeEnv : $this->env;
    }

    /**
     * Set variable
     *
     * @param string    $name
     * @param mixed     $value
     * @param boolean   $shadow
     * @param \stdClass $env
     */
    protected function set($name, $value, $shadow = false, $env = null)
    {
        $name = $this->normalizeName($name);

        if ($shadow) {
            $this->setRaw($name, $value, $env);
        } else {
            $this->setExisting($name, $value, $env);
        }
    }

    /**
     * Set existing variable
     *
     * @param string    $name
     * @param mixed     $value
     * @param \stdClass $env
     */
    protected function setExisting($name, $value, $env = null)
    {
        if (! isset($env)) {
            $env = $this->getStoreEnv();
        }

        $storeEnv = $env;

        $hasNamespace = $name[0] === '^' || $name[0] === '@' || $name[0] === '%';

        for (;;) {
            if (array_key_exists($name, $env->store)) {
                break;
            }

            if (! $hasNamespace && isset($env->marker)) {
                $env = $storeEnv;
                break;
            }

            if (! isset($env->parent)) {
                $env = $storeEnv;
                break;
            }

            $env = $env->parent;
        }

        $env->store[$name] = $value;
    }

    /**
     * Set raw variable
     *
     * @param string    $name
     * @param mixed     $value
     * @param \stdClass $env
     */
    protected function setRaw($name, $value, $env = null)
    {
        if (! isset($env)) {
            $env = $this->getStoreEnv();
        }

        $env->store[$name] = $value;
    }

    /**
     * Get variable
     *
     * @api
     *
     * @param string    $name
     * @param boolean   $shouldThrow
     * @param \stdClass $env
     *
     * @return mixed
     */
    public function get($name, $shouldThrow = true, $env = null)
    {
        $name = $this->normalizeName($name);

        if (! isset($env)) {
            $env = $this->getStoreEnv();
        }

        $hasNamespace = $name[0] === '^' || $name[0] === '@' || $name[0] === '%';

        for (;;) {
            if (array_key_exists($name, $env->store)) {
                return $env->store[$name];
            }

            if (! $hasNamespace && isset($env->marker)) {
                $env = $this->rootEnv;
                continue;
            }

            if (! isset($env->parent)) {
                break;
            }

            $env = $env->parent;
        }

        if ($shouldThrow) {
            $this->throwError("Undefined variable \$$name");
        }

        // found nothing
    }

    /**
     * Has variable?
     *
     * @param string    $name
     * @param \stdClass $env
     *
     * @return boolean
     */
    protected function has($name, $env = null)
    {
        return $this->get($name, false, $env) !== null;
    }

    /**
     * Inject variables
     *
     * @param array $args
     */
    protected function injectVariables(array $args)
    {
        if (empty($args)) {
            return;
        }

        $parser = new Parser(__METHOD__, false);

        foreach ($args as $name => $strValue) {
            if ($name[0] === '$') {
                $name = substr($name, 1);
            }

            if (! $parser->parseValue($strValue, $value)) {
                $value = $this->coerceValue($strValue);
            }

            $this->set($name, $value);
        }
    }

    /**
     * Set variables
     *
     * @api
     *
     * @param array $variables
     */
    public function setVariables(array $variables)
    {
        $this->registeredVars = array_merge($this->registeredVars, $variables);
    }

    /**
     * Unset variable
     *
     * @api
     *
     * @param string $name
     */
    public function unsetVariable($name)
    {
        unset($this->registeredVars[$name]);
    }

    /**
     * Adds to list of parsed files
     *
     * @api
     *
     * @param string $path
     */
    public function addParsedFile($path)
    {
        if (isset($path)) {
            $this->parsedFiles[realpath($path)] = filemtime($path);
        }
    }

    /**
     * Returns list of parsed files
     *
     * @api
     *
     * @return array
     */
    public function getParsedFiles()
    {
        return $this->parsedFiles;
    }

    /**
     * Add import path
     *
     * @api
     *
     * @param string $path
     */
    public function addImportPath($path)
    {
        if (! in_array($path, $this->importPaths)) {
            $this->importPaths[] = $path;
        }
    }

    /**
     * Set import paths
     *
     * @api
     *
     * @param string|array $path
     */
    public function setImportPaths($path)
    {
        $this->importPaths = (array)$path;
    }

    /**
     * Set number precision
     *
     * @api
     *
     * @param integer $numberPrecision
     */
    public function setNumberPrecision($numberPrecision)
    {
        $this->numberPrecision = $numberPrecision;
    }

    /**
     * Set formatter
     *
     * @api
     *
     * @param string $formatterName
     */
    public function setFormatter($formatterName)
    {
        $this->formatter = $formatterName;
    }

    /**
     * Set line number style
     *
     * @api
     *
     * @param string $lineNumberStyle
     */
    public function setLineNumberStyle($lineNumberStyle)
    {
        $this->lineNumberStyle = $lineNumberStyle;
    }

    /**
     * Register function
     *
     * @api
     *
     * @param string   $name
     * @param callable $func
     */
    public function registerFunction($name, $func)
    {
        $this->userFunctions[$this->normalizeName($name)] = $func;
    }

    /**
     * Unregister function
     *
     * @api
     *
     * @param string $name
     */
    public function unregisterFunction($name)
    {
        unset($this->userFunctions[$this->normalizeName($name)]);
    }

    /**
     * Import file
     *
     * @param string $path
     * @param array  $out
     */
    protected function importFile($path, $out)
    {
        // see if tree is cached
        $realPath = realpath($path);

        if (isset($this->importCache[$realPath])) {
            $this->handleImportLoop($realPath);

            $tree = $this->importCache[$realPath];
        } else {
            $code = file_get_contents($path);
            $parser = new Parser($path, false);
            $tree = $parser->parse($code);

            $this->addParsedFile($path);
            $this->importCache[$realPath] = $tree;
        }

        $pi = pathinfo($path);
        array_unshift($this->importPaths, $pi['dirname']);
        $this->compileChildren($tree->children, $out);
        array_shift($this->importPaths);
    }

    /**
     * Return the file path for an import url if it exists
     *
     * @api
     *
     * @param string $url
     *
     * @return string|null
     */
    public function findImport($url)
    {
        $urls = array();

        // for "normal" scss imports (ignore vanilla css and external requests)
        if (! preg_match('/\.css$|^https?:\/\//', $url)) {
            // try both normal and the _partial filename
            $urls = array($url, preg_replace('/[^\/]+$/', '_\0', $url));
        }

        foreach ($this->importPaths as $dir) {
            if (is_string($dir)) {
                // check urls for normal import paths
                foreach ($urls as $full) {
                    $full = $dir
                        . (! empty($dir) && substr($dir, -1) !== '/' ? '/' : '')
                        . $full;

                    if ($this->fileExists($file = $full . '.scss') ||
                        $this->fileExists($file = $full)
                    ) {
                        return $file;
                    }
                }
            } elseif (is_callable($dir)) {
                // check custom callback for import path
                $file = call_user_func($dir, $url, $this);

                if ($file !== null) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Throw error (exception)
     *
     * @api
     *
     * @param string $msg Message with optional sprintf()-style vararg parameters
     *
     * @throws \Exception
     */
    public function throwError($msg)
    {
        if (func_num_args() > 1) {
            $msg = call_user_func_array('sprintf', func_get_args());
        }

        if ($this->sourcePos >= 0 && isset($this->sourceParser)) {
            $this->sourceParser->throwParseError($msg, $this->sourcePos);
        }

        throw new \Exception($msg);
    }

    /**
     * Handle import loop
     *
     * @param string $name
     *
     * @throws \Exception
     */
    private function handleImportLoop($name)
    {
        for ($env = $this->env; $env; $env = $env->parent) {
            $file = $env->block->sourceParser->getSourceName();

            if (realpath($file) === $name) {
                $this->throwError(
                    'An @import loop has been found: %s imports %s',
                    $this->env->block->sourceParser->getSourceName(),
                    basename($file)
                );
            }
        }
    }

    /**
     * Does file exist?
     *
     * @param string $name
     *
     * @return boolean
     */
    protected function fileExists($name)
    {
        return is_file($name);
    }

    /**
     * Call built-in and registered (PHP) functions
     *
     * @param string $name
     * @param array  $args
     * @param array  $returnValue
     *
     * @return boolean Returns true if returnValue is set; otherwise, false
     */
    protected function callBuiltin($name, $args, &$returnValue)
    {
        // try a lib function
        $name = $this->normalizeName($name);

        if (isset($this->userFunctions[$name])) {
            // see if we can find a user function
            $fn = $this->userFunctions[$name];

            if ($name !== 'if' && $name !== 'call') {
                foreach ($args as &$val) {
                    $val = $this->reduce($val[1], true);
                }
            }

            $returnValue = call_user_func($fn, $args, $this);
        } else {
            $f = $this->getBuiltinFunction($name);

            if (is_callable($f)) {
                $libName = $f[1];

                $prototype = isset(self::$$libName) ? self::$$libName : null;
                $sorted = $this->sortArgs($prototype, $args);

                if ($name !== 'if' && $name !== 'call') {
                    foreach ($sorted as &$val) {
                        $val = $this->reduce($val, true);
                    }
                }

                $returnValue = call_user_func($f, $sorted, $this);
            }
        }

        if (isset($returnValue)) {
            $returnValue = $this->coerceValue($returnValue);

            return true;
        }

        return false;
    }

    /**
     * Get built-in function
     *
     * @param string $name Normalized name
     *
     * @return array
     */
    protected function getBuiltinFunction($name)
    {
        $libName = 'lib' . preg_replace_callback(
            '/_(.)/',
            function ($m) {
                return ucfirst($m[1]);
            },
            ucfirst($name)
        );

        return array($this, $libName);
    }

    /**
     * Sorts keyword arguments
     *
     * @todo Merge with applyArguments()?
     *
     * @param array $prototype
     * @param array $args
     *
     * @return array
     */
    protected function sortArgs($prototype, $args)
    {
        $keyArgs = array();
        $posArgs = array();

        foreach ($args as $arg) {
            list($key, $value) = $arg;

            $key = $key[1];

            if (empty($key)) {
                $posArgs[] = $value;
            } else {
                $keyArgs[$key] = $value;
            }
        }

        if (! isset($prototype)) {
            return $posArgs;
        }

        $finalArgs = array();

        foreach ($prototype as $i => $names) {
            if (isset($posArgs[$i])) {
                $finalArgs[] = $posArgs[$i];
                continue;
            }

            $set = false;

            foreach ((array)$names as $name) {
                if (isset($keyArgs[$name])) {
                    $finalArgs[] = $keyArgs[$name];
                    $set = true;
                    break;
                }
            }

            if (! $set) {
                $finalArgs[] = null;
            }
        }

        return $finalArgs;
    }

    /**
     * Apply argument values per definition
     *
     * @param array $argDef
     * @param array $argValues
     *
     * @throws \Exception
     */
    protected function applyArguments($argDef, $argValues)
    {
        $storeEnv = $this->getStoreEnv();

        $env = new \stdClass;
        $env->store = $storeEnv->store;

        $hasVariable = false;
        $args = array();

        foreach ($argDef as $i => $arg) {
            list($name, $default, $isVariable) = $argDef[$i];

            $args[$name] = array($i, $name, $default, $isVariable);
            $hasVariable |= $isVariable;
        }

        $keywordArgs = array();
        $deferredKeywordArgs = array();
        $remaining = array();

        // assign the keyword args
        foreach ((array) $argValues as $arg) {
            if (! empty($arg[0])) {
                if (! isset($args[$arg[0][1]])) {
                    if ($hasVariable) {
                        $deferredKeywordArgs[$arg[0][1]] = $arg[1];
                    } else {
                        $this->throwError("Mixin or function doesn't have an argument named $%s.", $arg[0][1]);
                    }
                } elseif ($args[$arg[0][1]][0] < count($remaining)) {
                    $this->throwError("The argument $%s was passed both by position and by name.", $arg[0][1]);
                } else {
                    $keywordArgs[$arg[0][1]] = $arg[1];
                }
            } elseif (count($keywordArgs)) {
                $this->throwError('Positional arguments must come before keyword arguments.');
            } elseif ($arg[2] === true) {
                $val = $this->reduce($arg[1], true);

                if ($val[0] === 'list') {
                    foreach ($val[2] as $name => $item) {
                        if (! is_numeric($name)) {
                            $keywordArgs[$name] = $item;
                        } else {
                            $remaining[] = $item;
                        }
                    }
                } else {
                    $remaining[] = $val;
                }
            } else {
                $remaining[] = $arg[1];
            }
        }

        foreach ($args as $arg) {
            list($i, $name, $default, $isVariable) = $arg;

            if ($isVariable) {
                $val = array('list', ',', array(), $isVariable);

                for ($count = count($remaining); $i < $count; $i++) {
                    $val[2][] = $remaining[$i];
                }

                foreach ($deferredKeywordArgs as $itemName => $item) {
                    $val[2][$itemName] = $item;
                }
            } elseif (isset($remaining[$i])) {
                $val = $remaining[$i];
            } elseif (isset($keywordArgs[$name])) {
                $val = $keywordArgs[$name];
            } elseif (! empty($default)) {
                continue;
            } else {
                $this->throwError("Missing argument $name");
            }

            $this->set($name, $this->reduce($val, true), true, $env);
        }

        $storeEnv->store = $env->store;

        foreach ($args as $arg) {
            list($i, $name, $default, $isVariable) = $arg;

            if ($isVariable || isset($remaining[$i]) || isset($keywordArgs[$name]) || empty($default)) {
                continue;
            }

            $this->set($name, $this->reduce($default, true), true);
        }
    }

    /**
     * Coerce a php value into a scss one
     *
     * @param mixed $value
     *
     * @return array
     */
    private function coerceValue($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? self::$true : self::$false;
        }

        if ($value === null) {
            $value = self::$null;
        }

        if (is_numeric($value)) {
            return array('number', $value, '');
        }

        if ($value === '') {
            return self::$emptyString;
        }

        return array('keyword', $value);
    }

    /**
     * Coerce unit on number to be normalized
     *
     * @param array  $number
     * @param string $unit
     *
     * @return array
     */
    protected function coerceUnit($number, $unit)
    {
        list(, $value, $baseUnit) = $number;

        if (isset(self::$unitTable[$baseUnit][$unit])) {
            $value = $value * self::$unitTable[$baseUnit][$unit];
        }

        return array('number', $value, $unit);
    }

    /**
     * Coerce something to map
     *
     * @param array $item
     *
     * @return array
     */
    protected function coerceMap($item)
    {
        if ($item[0] === 'map') {
            return $item;
        }

        if ($item === self::$emptyList) {
            return self::$emptyMap;
        }

        return array('map', array($item), array(self::$null));
    }

    /**
     * Coerce something to list
     *
     * @param array $item
     *
     * @return array
     */
    protected function coerceList($item, $delim = ',')
    {
        if (isset($item) && $item[0] === 'list') {
            return $item;
        }

        if (isset($item) && $item[0] === 'map') {
            $keys = $item[1];
            $values = $item[2];
            $list = array();

            for ($i = 0, $s = count($keys); $i < $s; $i++) {
                $key = $keys[$i];
                $value = $values[$i];

                $list[] = array('list', '', array(array('keyword', $this->compileValue($key)), $value));
            }

            return array('list', ',', $list);
        }

        return array('list', $delim, ! isset($item) ? array(): array($item));
    }

    /**
     * Coerce color for expression
     *
     * @param array $value
     *
     * @return array|null
     */
    protected function coerceForExpression($value)
    {
        if ($color = $this->coerceColor($value)) {
            return $color;
        }

        return $value;
    }

    /**
     * Coerce value to color
     *
     * @param array $value
     *
     * @return array|null
     */
    protected function coerceColor($value)
    {
        switch ($value[0]) {
            case 'color':
                return $value;

            case 'keyword':
                $name = strtolower($value[1]);

                if (isset(Colors::$cssColors[$name])) {
                    $rgba = explode(',', Colors::$cssColors[$name]);

                    return isset($rgba[3])
                        ? array('color', (int) $rgba[0], (int) $rgba[1], (int) $rgba[2], (int) $rgba[3])
                        : array('color', (int) $rgba[0], (int) $rgba[1], (int) $rgba[2]);
                }

                return null;
        }

        return null;
    }

    /**
     * Coerce value to string
     *
     * @param array $value
     *
     * @return array|null
     */
    protected function coerceString($value)
    {
        if ($value[0] === 'string') {
            return $value;
        }

        return array('string', '', array($this->compileValue($value)));
    }

    /**
     * Coerce value to a percentage
     *
     * @param array $value
     *
     * @return integer|float
     */
    protected function coercePercent($value)
    {
        if ($value[0] === 'number') {
            if ($value[2] === '%') {
                return $value[1] / 100;
            }

            return $value[1];
        }

        return 0;
    }

    /**
     * Assert value is a map
     *
     * @api
     *
     * @param array $value
     *
     * @return array
     *
     * @throws \Exception
     */
    public function assertMap($value)
    {
        $value = $this->coerceMap($value);

        if ($value[0] !== 'map') {
            $this->throwError('expecting map');
        }

        return $value;
    }

    /**
     * Assert value is a list
     *
     * @api
     *
     * @param array $value
     *
     * @return array
     *
     * @throws \Exception
     */
    public function assertList($value)
    {
        if ($value[0] !== 'list') {
            $this->throwError('expecting list');
        }

        return $value;
    }

    /**
     * Assert value is a color
     *
     * @api
     *
     * @param array $value
     *
     * @return array
     *
     * @throws \Exception
     */
    public function assertColor($value)
    {
        if ($color = $this->coerceColor($value)) {
            return $color;
        }

        $this->throwError('expecting color');
    }

    /**
     * Assert value is a number
     *
     * @api
     *
     * @param array $value
     *
     * @return integer|float
     *
     * @throws \Exception
     */
    public function assertNumber($value)
    {
        if ($value[0] !== 'number') {
            $this->throwError('expecting number');
        }

        return $value[1];
    }

    /**
     * Make sure a color's components don't go out of bounds
     *
     * @param array $c
     *
     * @return array
     */
    protected function fixColor($c)
    {
        foreach (range(1, 3) as $i) {
            if ($c[$i] < 0) {
                $c[$i] = 0;
            }

            if ($c[$i] > 255) {
                $c[$i] = 255;
            }
        }

        return $c;
    }

    /**
     * Convert RGB to HSL
     *
     * @api
     *
     * @param integer $red
     * @param integer $green
     * @param integer $blue
     *
     * @return array
     */
    public function toHSL($red, $green, $blue)
    {
        $min = min($red, $green, $blue);
        $max = max($red, $green, $blue);

        $l = $min + $max;
        $d = $max - $min;

        if ((int) $d === 0) {
            $h = $s = 0;
        } else {
            if ($l < 255) {
                $s = $d / $l;
            } else {
                $s = $d / (510 - $l);
            }

            if ($red == $max) {
                $h = 60 * ($green - $blue) / $d;
            } elseif ($green == $max) {
                $h = 60 * ($blue - $red) / $d + 120;
            } elseif ($blue == $max) {
                $h = 60 * ($red - $green) / $d + 240;
            }
        }

        return array('hsl', fmod($h, 360), $s * 100, $l / 5.1);
    }

    /**
     * Hue to RGB helper
     *
     * @param float $m1
     * @param float $m2
     * @param float $h
     *
     * @return float
     */
    private function hueToRGB($m1, $m2, $h)
    {
        if ($h < 0) {
            $h += 1;
        } elseif ($h > 1) {
            $h -= 1;
        }

        if ($h * 6 < 1) {
            return $m1 + ($m2 - $m1) * $h * 6;
        }

        if ($h * 2 < 1) {
            return $m2;
        }

        if ($h * 3 < 2) {
            return $m1 + ($m2 - $m1) * (2/3 - $h) * 6;
        }

        return $m1;
    }

    /**
     * Convert HSL to RGB
     *
     * @api
     *
     * @param integer $hue        H from 0 to 360
     * @param integer $saturation S from 0 to 100
     * @param integer $lightness  L from 0 to 100
     *
     * @return array
     */
    public function toRGB($hue, $saturation, $lightness)
    {
        if ($hue < 0) {
            $hue += 360;
        }

        $h = $hue / 360;
        $s = min(100, max(0, $saturation)) / 100;
        $l = min(100, max(0, $lightness)) / 100;

        $m2 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
        $m1 = $l * 2 - $m2;

        $r = $this->hueToRGB($m1, $m2, $h + 1/3) * 255;
        $g = $this->hueToRGB($m1, $m2, $h) * 255;
        $b = $this->hueToRGB($m1, $m2, $h - 1/3) * 255;

        $out = array('color', $r, $g, $b);

        return $out;
    }

    // Built in functions

    //protected static $libCall = array('name', 'args...');
    protected function libCall($args)
    {
        $name = $this->compileStringContent($this->coerceString($this->reduce(array_shift($args), true)));

        return $this->reduce(
            array(
                'fncall',
                $name,
                array_map(
                    function ($a) {
                        return array(null, $a);
                    },
                    $args
                )
            )
        );
    }

    protected static $libIf = array('condition', 'if-true', 'if-false');
    protected function libIf($args)
    {
        list($cond, $t, $f) = $args;

        if (! $this->isTruthy($this->reduce($cond, true))) {
            return $this->reduce($f, true);
        }

        return $this->reduce($t, true);
    }

    protected static $libIndex = array('list', 'value');
    protected function libIndex($args)
    {
        list($list, $value) = $args;

        if ($value[0] === 'map') {
            return self::$null;
        }

        if ($list[0] === 'map') {
            $list = $this->coerceList($list, ' ');
        }

        if ($list[0] !== 'list') {
            return self::$null;
        }

        $values = array();

        foreach ($list[2] as $item) {
            $values[] = $this->normalizeValue($item);
        }

        $key = array_search($this->normalizeValue($value), $values);

        return false === $key ? self::$null : $key + 1;
    }

    protected static $libRgb = array('red', 'green', 'blue');
    protected function libRgb($args)
    {
        list($r, $g, $b) = $args;

        return array('color', $r[1], $g[1], $b[1]);
    }

    protected static $libRgba = array(
        array('red', 'color'),
        'green', 'blue', 'alpha');
    protected function libRgba($args)
    {
        if ($color = $this->coerceColor($args[0])) {
            // workaround https://github.com/facebook/hhvm/issues/5457
            reset($args);

            $num = ! isset($args[1]) ? $args[3] : $args[1];
            $alpha = $this->assertNumber($num);
            $color[4] = $alpha;

            return $color;
        }

        list($r, $g, $b, $a) = $args;

        return array('color', $r[1], $g[1], $b[1], $a[1]);
    }

    // helper function for adjust_color, change_color, and scale_color
    protected function alterColor($args, $fn)
    {
        $color = $this->assertColor($args[0]);

        // workaround https://github.com/facebook/hhvm/issues/5457
        reset($args);

        foreach (array(1, 2, 3, 7) as $i) {
            if (isset($args[$i])) {
                $val = $this->assertNumber($args[$i]);
                $ii = $i === 7 ? 4 : $i; // alpha
                $color[$ii] = call_user_func($fn, isset($color[$ii]) ? $color[$ii] : 0, $val, $i);
            }
        }

        if (isset($args[4]) || isset($args[5]) || isset($args[6])) {
            $hsl = $this->toHSL($color[1], $color[2], $color[3]);

            foreach (array(4, 5, 6) as $i) {
                if (isset($args[$i])) {
                    $val = $this->assertNumber($args[$i]);
                    $hsl[$i - 3] = call_user_func($fn, $hsl[$i - 3], $val, $i);
                }
            }

            $rgb = $this->toRGB($hsl[1], $hsl[2], $hsl[3]);

            if (isset($color[4])) {
                $rgb[4] = $color[4];
            }

            $color = $rgb;
        }

        return $color;
    }

    protected static $libAdjustColor = array(
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    );
    protected function libAdjustColor($args)
    {
        return $this->alterColor($args, function ($base, $alter, $i) {
            return $base + $alter;
        });
    }

    protected static $libChangeColor = array(
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    );
    protected function libChangeColor($args)
    {
        return $this->alterColor($args, function ($base, $alter, $i) {
            return $alter;
        });
    }

    protected static $libScaleColor = array(
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    );
    protected function libScaleColor($args)
    {
        return $this->alterColor($args, function ($base, $scale, $i) {
            // 1, 2, 3 - rgb
            // 4, 5, 6 - hsl
            // 7 - a
            switch ($i) {
                case 1:
                case 2:
                case 3:
                    $max = 255;
                    break;

                case 4:
                    $max = 360;
                    break;

                case 7:
                    $max = 1;
                    break;

                default:
                    $max = 100;
            }

            $scale = $scale / 100;

            if ($scale < 0) {
                return $base * $scale + $base;
            }

            return ($max - $base) * $scale + $base;
        });
    }

    protected static $libIeHexStr = array('color');
    protected function libIeHexStr($args)
    {
        $color = $this->coerceColor($args[0]);
        $color[4] = isset($color[4]) ? round(255*$color[4]) : 255;

        return sprintf('#%02X%02X%02X%02X', $color[4], $color[1], $color[2], $color[3]);
    }

    protected static $libRed = array('color');
    protected function libRed($args)
    {
        $color = $this->coerceColor($args[0]);

        return $color[1];
    }

    protected static $libGreen = array('color');
    protected function libGreen($args)
    {
        $color = $this->coerceColor($args[0]);

        return $color[2];
    }

    protected static $libBlue = array('color');
    protected function libBlue($args)
    {
        $color = $this->coerceColor($args[0]);

        return $color[3];
    }

    protected static $libAlpha = array('color');
    protected function libAlpha($args)
    {
        if ($color = $this->coerceColor($args[0])) {
            return isset($color[4]) ? $color[4] : 1;
        }

        // this might be the IE function, so return value unchanged
        return null;
    }

    protected static $libOpacity = array('color');
    protected function libOpacity($args)
    {
        $value = $args[0];

        if ($value[0] === 'number') {
            return null;
        }

        return $this->libAlpha($args);
    }

    // mix two colors
    protected static $libMix = array('color-1', 'color-2', 'weight');
    protected function libMix($args)
    {
        list($first, $second, $weight) = $args;

        $first = $this->assertColor($first);
        $second = $this->assertColor($second);

        if (! isset($weight)) {
            $weight = 0.5;
        } else {
            $weight = $this->coercePercent($weight);
        }

        $firstAlpha = isset($first[4]) ? $first[4] : 1;
        $secondAlpha = isset($second[4]) ? $second[4] : 1;

        $w = $weight * 2 - 1;
        $a = $firstAlpha - $secondAlpha;

        $w1 = (($w * $a === -1 ? $w : ($w + $a) / (1 + $w * $a)) + 1) / 2.0;
        $w2 = 1.0 - $w1;

        $new = array('color',
            $w1 * $first[1] + $w2 * $second[1],
            $w1 * $first[2] + $w2 * $second[2],
            $w1 * $first[3] + $w2 * $second[3],
        );

        if ($firstAlpha != 1.0 || $secondAlpha != 1.0) {
            $new[] = $firstAlpha * $weight + $secondAlpha * ($weight - 1);
        }

        return $this->fixColor($new);
    }

    protected static $libHsl = array('hue', 'saturation', 'lightness');
    protected function libHsl($args)
    {
        list($h, $s, $l) = $args;

        return $this->toRGB($h[1], $s[1], $l[1]);
    }

    protected static $libHsla = array('hue', 'saturation', 'lightness', 'alpha');
    protected function libHsla($args)
    {
        list($h, $s, $l, $a) = $args;

        $color = $this->toRGB($h[1], $s[1], $l[1]);
        $color[4] = $a[1];

        return $color;
    }

    protected static $libHue = array('color');
    protected function libHue($args)
    {
        $color = $this->assertColor($args[0]);
        $hsl = $this->toHSL($color[1], $color[2], $color[3]);

        return array('number', $hsl[1], 'deg');
    }

    protected static $libSaturation = array('color');
    protected function libSaturation($args)
    {
        $color = $this->assertColor($args[0]);
        $hsl = $this->toHSL($color[1], $color[2], $color[3]);

        return array('number', $hsl[2], '%');
    }

    protected static $libLightness = array('color');
    protected function libLightness($args)
    {
        $color = $this->assertColor($args[0]);
        $hsl = $this->toHSL($color[1], $color[2], $color[3]);

        return array('number', $hsl[3], '%');
    }

    protected function adjustHsl($color, $idx, $amount)
    {
        $hsl = $this->toHSL($color[1], $color[2], $color[3]);
        $hsl[$idx] += $amount;
        $out = $this->toRGB($hsl[1], $hsl[2], $hsl[3]);

        if (isset($color[4])) {
            $out[4] = $color[4];
        }

        return $out;
    }

    protected static $libAdjustHue = array('color', 'degrees');
    protected function libAdjustHue($args)
    {
        $color = $this->assertColor($args[0]);
        $degrees = $this->assertNumber($args[1]);

        return $this->adjustHsl($color, 1, $degrees);
    }

    protected static $libLighten = array('color', 'amount');
    protected function libLighten($args)
    {
        $color = $this->assertColor($args[0]);
        $amount = Util::checkRange('amount', new Range(0, 100), $args[1], '%');

        return $this->adjustHsl($color, 3, $amount);
    }

    protected static $libDarken = array('color', 'amount');
    protected function libDarken($args)
    {
        $color = $this->assertColor($args[0]);
        $amount = Util::checkRange('amount', new Range(0, 100), $args[1], '%');

        return $this->adjustHsl($color, 3, -$amount);
    }

    protected static $libSaturate = array('color', 'amount');
    protected function libSaturate($args)
    {
        $value = $args[0];

        if ($value[0] === 'number') {
            return null;
        }

        $color = $this->assertColor($value);
        $amount = 100*$this->coercePercent($args[1]);

        return $this->adjustHsl($color, 2, $amount);
    }

    protected static $libDesaturate = array('color', 'amount');
    protected function libDesaturate($args)
    {
        $color = $this->assertColor($args[0]);
        $amount = 100*$this->coercePercent($args[1]);

        return $this->adjustHsl($color, 2, -$amount);
    }

    protected static $libGrayscale = array('color');
    protected function libGrayscale($args)
    {
        $value = $args[0];

        if ($value[0] === 'number') {
            return null;
        }

        return $this->adjustHsl($this->assertColor($value), 2, -100);
    }

    protected static $libComplement = array('color');
    protected function libComplement($args)
    {
        return $this->adjustHsl($this->assertColor($args[0]), 1, 180);
    }

    protected static $libInvert = array('color');
    protected function libInvert($args)
    {
        $value = $args[0];

        if ($value[0] === 'number') {
            return null;
        }

        $color = $this->assertColor($value);
        $color[1] = 255 - $color[1];
        $color[2] = 255 - $color[2];
        $color[3] = 255 - $color[3];

        return $color;
    }

    // increases opacity by amount
    protected static $libOpacify = array('color', 'amount');
    protected function libOpacify($args)
    {
        $color = $this->assertColor($args[0]);
        $amount = $this->coercePercent($args[1]);

        $color[4] = (isset($color[4]) ? $color[4] : 1) + $amount;
        $color[4] = min(1, max(0, $color[4]));

        return $color;
    }

    protected static $libFadeIn = array('color', 'amount');
    protected function libFadeIn($args)
    {
        return $this->libOpacify($args);
    }

    // decreases opacity by amount
    protected static $libTransparentize = array('color', 'amount');
    protected function libTransparentize($args)
    {
        $color = $this->assertColor($args[0]);
        $amount = $this->coercePercent($args[1]);

        $color[4] = (isset($color[4]) ? $color[4] : 1) - $amount;
        $color[4] = min(1, max(0, $color[4]));

        return $color;
    }

    protected static $libFadeOut = array('color', 'amount');
    protected function libFadeOut($args)
    {
        return $this->libTransparentize($args);
    }

    protected static $libUnquote = array('string');
    protected function libUnquote($args)
    {
        $str = $args[0];

        if ($str[0] === 'string') {
            $str[1] = '';
        }

        return $str;
    }

    protected static $libQuote = array('string');
    protected function libQuote($args)
    {
        $value = $args[0];

        if ($value[0] === 'string' && ! empty($value[1])) {
            return $value;
        }

        return array('string', '"', array($value));
    }

    protected static $libPercentage = array('value');
    protected function libPercentage($args)
    {
        return array('number',
            $this->coercePercent($args[0]) * 100,
            '%');
    }

    protected static $libRound = array('value');
    protected function libRound($args)
    {
        $num = $args[0];
        $num[1] = round($num[1]);

        return $num;
    }

    protected static $libFloor = array('value');
    protected function libFloor($args)
    {
        $num = $args[0];
        $num[1] = floor($num[1]);

        return $num;
    }

    protected static $libCeil = array('value');
    protected function libCeil($args)
    {
        $num = $args[0];
        $num[1] = ceil($num[1]);

        return $num;
    }

    protected static $libAbs = array('value');
    protected function libAbs($args)
    {
        $num = $args[0];
        $num[1] = abs($num[1]);

        return $num;
    }

    protected function libMin($args)
    {
        $numbers = $this->getNormalizedNumbers($args);
        $min = null;

        foreach ($numbers as $key => $number) {
            if (null === $min || $number[1] <= $min[1]) {
                $min = array($key, $number[1]);
            }
        }

        return $args[$min[0]];
    }

    protected function libMax($args)
    {
        $numbers = $this->getNormalizedNumbers($args);
        $max = null;

        foreach ($numbers as $key => $number) {
            if (null === $max || $number[1] >= $max[1]) {
                $max = array($key, $number[1]);
            }
        }

        return $args[$max[0]];
    }

    /**
     * Helper to normalize args containing numbers
     *
     * @param array $args
     *
     * @return array
     */
    protected function getNormalizedNumbers($args)
    {
        $unit = null;
        $originalUnit = null;
        $numbers = array();

        foreach ($args as $key => $item) {
            if ('number' !== $item[0]) {
                $this->throwError('%s is not a number', $item[0]);
            }

            $number = $this->normalizeNumber($item);

            if (null === $unit) {
                $unit = $number[2];
                $originalUnit = $item[2];
            } elseif ($unit !== $number[2]) {
                $this->throwError('Incompatible units: "%s" and "%s".', $originalUnit, $item[2]);
            }

            $numbers[$key] = $number;
        }

        return $numbers;
    }

    protected static $libLength = array('list');
    protected function libLength($args)
    {
        $list = $this->coerceList($args[0]);

        return count($list[2]);
    }

    // TODO: need a way to declare this built-in as varargs
    //protected static $libListSeparator = array('list...');
    protected function libListSeparator($args)
    {
        if (count($args) > 1) {
            return 'comma';
        }

        $list = $this->coerceList($args[0]);

        if (count($list[2]) <= 1) {
            return 'space';
        }

        if ($list[1] === ',') {
            return 'comma';
        }

        return 'space';
    }

    protected static $libNth = array('list', 'n');
    protected function libNth($args)
    {
        $list = $this->coerceList($args[0]);
        $n = $this->assertNumber($args[1]) - 1;

        return isset($list[2][$n]) ? $list[2][$n] : self::$defaultValue;
    }

    protected static $libSetNth = array('list', 'n', 'value');
    protected function libSetNth($args)
    {
        $list = $this->coerceList($args[0]);
        $n = $this->assertNumber($args[1]) - 1;

        if (! isset($list[2][$n])) {
            $this->throwError('Invalid argument for "n"');
        }

        $list[2][$n] = $args[2];

        return $list;
    }

    protected static $libMapGet = array('map', 'key');
    protected function libMapGet($args)
    {
        $map = $this->assertMap($args[0]);
        $key = $this->compileStringContent($this->coerceString($args[1]));

        for ($i = count($map[1]) - 1; $i >= 0; $i--) {
            if ($key === $this->compileStringContent($this->coerceString($map[1][$i]))) {
                return $map[2][$i];
            }
        }

        return self::$null;
    }

    protected static $libMapKeys = array('map');
    protected function libMapKeys($args)
    {
        $map = $this->assertMap($args[0]);
        $keys = $map[1];

        return array('list', ',', $keys);
    }

    protected static $libMapValues = array('map');
    protected function libMapValues($args)
    {
        $map = $this->assertMap($args[0]);
        $values = $map[2];

        return array('list', ',', $values);
    }

    protected static $libMapRemove = array('map', 'key');
    protected function libMapRemove($args)
    {
        $map = $this->assertMap($args[0]);
        $key = $this->compileStringContent($this->coerceString($args[1]));

        for ($i = count($map[1]) - 1; $i >= 0; $i--) {
            if ($key === $this->compileStringContent($this->coerceString($map[1][$i]))) {
                array_splice($map[1], $i, 1);
                array_splice($map[2], $i, 1);
            }
        }

        return $map;
    }

    protected static $libMapHasKey = array('map', 'key');
    protected function libMapHasKey($args)
    {
        $map = $this->assertMap($args[0]);
        $key = $this->compileStringContent($this->coerceString($args[1]));

        for ($i = count($map[1]) - 1; $i >= 0; $i--) {
            if ($key === $this->compileStringContent($this->coerceString($map[1][$i]))) {
                return self::$true;
            }
        }

        return self::$false;
    }

    protected static $libMapMerge = array('map-1', 'map-2');
    protected function libMapMerge($args)
    {
        $map1 = $this->assertMap($args[0]);
        $map2 = $this->assertMap($args[1]);

        return array('map', array_merge($map1[1], $map2[1]), array_merge($map1[2], $map2[2]));
    }

    protected function listSeparatorForJoin($list1, $sep)
    {
        if (! isset($sep)) {
            return $list1[1];
        }

        switch ($this->compileValue($sep)) {
            case 'comma':
                return ',';

            case 'space':
                return '';

            default:
                return $list1[1];
        }
    }

    protected static $libJoin = array('list1', 'list2', 'separator');
    protected function libJoin($args)
    {
        list($list1, $list2, $sep) = $args;

        $list1 = $this->coerceList($list1, ' ');
        $list2 = $this->coerceList($list2, ' ');
        $sep = $this->listSeparatorForJoin($list1, $sep);

        return array('list', $sep, array_merge($list1[2], $list2[2]));
    }

    protected static $libAppend = array('list', 'val', 'separator');
    protected function libAppend($args)
    {
        list($list1, $value, $sep) = $args;

        $list1 = $this->coerceList($list1, ' ');
        $sep = $this->listSeparatorForJoin($list1, $sep);

        return array('list', $sep, array_merge($list1[2], array($value)));
    }

    protected function libZip($args)
    {
        foreach ($args as $arg) {
            $this->assertList($arg);
        }

        $lists = array();
        $firstList = array_shift($args);

        foreach ($firstList[2] as $key => $item) {
            $list = array('list', '', array($item));

            foreach ($args as $arg) {
                if (isset($arg[2][$key])) {
                    $list[2][] = $arg[2][$key];
                } else {
                    break 2;
                }
            }

            $lists[] = $list;
        }

        return array('list', ',', $lists);
    }

    protected static $libTypeOf = array('value');
    protected function libTypeOf($args)
    {
        $value = $args[0];

        switch ($value[0]) {
            case 'keyword':
                if ($value === self::$true || $value === self::$false) {
                    return 'bool';
                }

                if ($this->coerceColor($value)) {
                    return 'color';
                }

                // fall-thru
            case 'function':
                return 'string';

            case 'list':
                if (isset($value[3]) && $value[3]) {
                    return 'arglist';
                }

                // fall-thru
            default:
                return $value[0];
        }
    }

    protected static $libUnit = array('number');
    protected function libUnit($args)
    {
        $num = $args[0];

        if ($num[0] === 'number') {
            return array('string', '"', array($num[2]));
        }

        return '';
    }

    protected static $libUnitless = array('number');
    protected function libUnitless($args)
    {
        $value = $args[0];

        return $value[0] === 'number' && empty($value[2]);
    }

    protected static $libComparable = array('number-1', 'number-2');
    protected function libComparable($args)
    {
        list($number1, $number2) = $args;

        if (! isset($number1[0]) || $number1[0] !== 'number' || ! isset($number2[0]) || $number2[0] !== 'number') {
            $this->throwError('Invalid argument(s) for "comparable"');
        }

        $number1 = $this->normalizeNumber($number1);
        $number2 = $this->normalizeNumber($number2);

        return $number1[2] === $number2[2] || $number1[2] === '' || $number2[2] === '';
    }

    protected static $libStrIndex = array('string', 'substring');
    protected function libStrIndex($args)
    {
        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        $substring = $this->coerceString($args[1]);
        $substringContent = $this->compileStringContent($substring);

        $result = strpos($stringContent, $substringContent);

        return $result === false ? self::$null : array('number', $result + 1, '');
    }

    protected static $libStrInsert = array('string', 'insert', 'index');
    protected function libStrInsert($args)
    {
        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        $insert = $this->coerceString($args[1]);
        $insertContent = $this->compileStringContent($insert);

        list(, $index) = $args[2];

        $string[2] = array(substr_replace($stringContent, $insertContent, $index - 1, 0));

        return $string;
    }

    protected static $libStrLength = array('string');
    protected function libStrLength($args)
    {
        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        return array('number', strlen($stringContent), '');
    }

    protected static $libStrSlice = array('string', 'start-at', 'end-at');
    protected function libStrSlice($args)
    {
        if ($args[2][1] == 0) {
            return self::$emptyString;
        }

        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        $start = (int) $args[1][1] ?: 1;
        $end = (int) $args[2][1];

        $string[2] = array(substr($stringContent, $start - 1, $end < 0 ? $end : $end - $start + 1));

        return $string;
    }

    protected static $libToLowerCase = array('string');
    protected function libToLowerCase($args)
    {
        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        $string[2] = array(mb_strtolower($stringContent));

        return $string;
    }

    protected static $libToUpperCase = array('string');
    protected function libToUpperCase($args)
    {
        $string = $this->coerceString($args[0]);
        $stringContent = $this->compileStringContent($string);

        $string[2] = array(mb_strtoupper($stringContent));

        return $string;
    }

    protected static $libFeatureExists = array('feature');
    protected function libFeatureExists($args)
    {
        /*
         * The following features not not (yet) supported:
         * - global-variable-shadowing
         * - extend-selector-pseudoclass
         * - units-level-3
         * - at-error
         */
        return self::$false;
    }

    protected static $libFunctionExists = array('name');
    protected function libFunctionExists($args)
    {
        $string = $this->coerceString($args[0]);
        $name = $this->compileStringContent($string);

        // user defined functions
        if ($this->has(self::$namespaces['function'] . $name)) {
            return self::$true;
        }

        $name = $this->normalizeName($name);

        if (isset($this->userFunctions[$name])) {
            return self::$true;
        }

        // built-in functions
        $f = $this->getBuiltinFunction($name);

        return $this->toBool(is_callable($f));
    }

    protected static $libGlobalVariableExists = array('name');
    protected function libGlobalVariableExists($args)
    {
        $string = $this->coerceString($args[0]);
        $name = $this->compileStringContent($string);

        return $this->has($name, $this->rootEnv) ? self::$true : self::$false;
    }

    protected static $libMixinExists = array('name');
    protected function libMixinExists($args)
    {
        $string = $this->coerceString($args[0]);
        $name = $this->compileStringContent($string);

        return $this->has(self::$namespaces['mixin'] . $name) ? self::$true : self::$false;
    }

    protected static $libVariableExists = array('name');
    protected function libVariableExists($args)
    {
        $string = $this->coerceString($args[0]);
        $name = $this->compileStringContent($string);

        return $this->has($name) ? self::$true : self::$false;
    }

    /**
     * Workaround IE7's content counter bug.
     *
     * @param array $args
     */
    protected function libCounter($args)
    {
        $list = array_map(array($this, 'compileValue'), $args);

        return array('string', '', array('counter(' . implode(',', $list) . ')'));
    }

    protected function libRandom($args)
    {
        if (isset($args[0])) {
            $n = $this->assertNumber($args[0]);

            if ($n < 1) {
                $this->throwError("limit must be greater than or equal to 1");
            }

            return array('number', mt_rand(1, $n), '');
        }

        return array('number', mt_rand(1, mt_getrandmax()), '');
    }

    protected function libUniqueId()
    {
        static $id;

        if (! isset($id)) {
            $id = mt_rand(0, pow(36, 8));
        }

        $id += mt_rand(0, 10) + 1;

        return array('string', '', array('u' . str_pad(base_convert($id, 10, 36), 8, '0', STR_PAD_LEFT)));
    }
}
