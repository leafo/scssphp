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

use Leafo\ScssPhp\Compiler;

/**
 * SCSS parser
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class Parser
{
    const SOURCE_POSITION = -1;
    const SOURCE_PARSER   = -2;

    /**
     * @var array
     */
    protected static $precedence = array(
        '=' => 0,
        'or' => 1,
        'and' => 2,
        '==' => 3,
        '!=' => 3,
        '<=' => 4,
        '>=' => 4,
        '<' => 4,
        '>' => 4,
        '+' => 5,
        '-' => 5,
        '*' => 6,
        '/' => 6,
        '%' => 6,
    );

    /**
     * @var array
     */
    protected static $operators = array(
        '+',
        '-',
        '*',
        '/',
        '%',
        '==',
        '!=',
        '<=',
        '>=',
        '<',
        '>',
        'and',
        'or',
    );

    protected static $operatorStr;
    protected static $whitePattern;
    protected static $commentMulti;

    protected static $commentSingle = '//';
    protected static $commentMultiLeft = '/*';
    protected static $commentMultiRight = '*/';

    private $sourceName;
    private $rootParser;
    private $charset;
    private $count;
    private $env;
    private $inParens;
    private $eatWhiteDefault;
    private $buffer;

    /**
     * Constructor
     *
     * @param string  $sourceName
     * @param boolean $rootParser
     */
    public function __construct($sourceName = null, $rootParser = true)
    {
        $this->sourceName = $sourceName ?: '(stdin)';
        $this->rootParser = $rootParser;
        $this->charset = null;

        if (empty(self::$operatorStr)) {
            self::$operatorStr = $this->makeOperatorStr(self::$operators);

            $commentSingle = $this->pregQuote(self::$commentSingle);
            $commentMultiLeft = $this->pregQuote(self::$commentMultiLeft);
            $commentMultiRight = $this->pregQuote(self::$commentMultiRight);
            self::$commentMulti = $commentMultiLeft . '.*?' . $commentMultiRight;
            self::$whitePattern = '/' . $commentSingle . '[^\n]*\s*|(' . self::$commentMulti . ')\s*|\s+/Ais';
        }
    }

    /**
     * Make operator regex
     *
     * @param array $operators
     *
     * @return string
     */
    protected static function makeOperatorStr($operators)
    {
        return '('
            . implode('|', array_map(array('Leafo\ScssPhp\Parser', 'pregQuote'), $operators))
            . ')';
    }

    /**
     * Parser buffer
     *
     * @param string $buffer;
     *
     * @return \stdClass
     */
    public function parse($buffer)
    {
        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = $buffer;

        $this->pushBlock(null); // root block

        $this->whitespace();
        $this->pushBlock(null);
        $this->popBlock();

        while ($this->parseChunk()) {
            ;
        }

        if ($this->count !== strlen($this->buffer)) {
            $this->throwParseError();
        }

        if (! empty($this->env->parent)) {
            $this->throwParseError('unclosed block');
        }

        if ($this->charset) {
            array_unshift($this->env->children, $this->charset);
        }

        $this->env->isRoot    = true;

        return $this->env;
    }

    /**
     * Parse a value or value list
     *
     * @param string $buffer
     * @param string $out
     *
     * @return boolean
     */
    public function parseValue($buffer, &$out)
    {
        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = (string) $buffer;

        return $this->valueList($out);
    }

    /**
     * Parse a selector or selector list
     *
     * @param string $buffer
     * @param string $out
     *
     * @return boolean
     */
    public function parseSelector($buffer, &$out)
    {
        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = (string) $buffer;

        return $this->selectors($out);
    }

    /**
     * Parse a single chunk off the head of the buffer and append it to the
     * current parse environment.
     *
     * Returns false when the buffer is empty, or when there is an error.
     *
     * This function is called repeatedly until the entire document is
     * parsed.
     *
     * This parser is most similar to a recursive descent parser. Single
     * functions represent discrete grammatical rules for the language, and
     * they are able to capture the text that represents those rules.
     *
     * Consider the function Compiler::keyword(). (All parse functions are
     * structured the same.)
     *
     * The function takes a single reference argument. When calling the
     * function it will attempt to match a keyword on the head of the buffer.
     * If it is successful, it will place the keyword in the referenced
     * argument, advance the position in the buffer, and return true. If it
     * fails then it won't advance the buffer and it will return false.
     *
     * All of these parse functions are powered by Compiler::match(), which behaves
     * the same way, but takes a literal regular expression. Sometimes it is
     * more convenient to use match instead of creating a new function.
     *
     * Because of the format of the functions, to parse an entire string of
     * grammatical rules, you can chain them together using &&.
     *
     * But, if some of the rules in the chain succeed before one fails, then
     * the buffer position will be left at an invalid state. In order to
     * avoid this, Compiler::seek() is used to remember and set buffer positions.
     *
     * Before parsing a chain, use $s = $this->seek() to remember the current
     * position into $s. Then if a chain fails, use $this->seek($s) to
     * go back where we started.
     *
     * @return boolean
     */
    protected function parseChunk()
    {
        $s = $this->seek();

        // the directives
        if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] === '@') {
            if ($this->literal('@at-root') &&
                ($this->selectors($selector) || true) &&
                ($this->map($with) || true) &&
                $this->literal('{')
            ) {
                $atRoot = $this->pushSpecialBlock('at-root', $s);
                $atRoot->selector = $selector;
                $atRoot->with = $with;

                return true;
            }

            $this->seek($s);

            if ($this->literal('@media') && $this->mediaQueryList($mediaQueryList) && $this->literal('{')) {
                $media = $this->pushSpecialBlock('media', $s);
                $media->queryList = $mediaQueryList[2];

                return true;
            }

            $this->seek($s);

            if ($this->literal('@mixin') &&
                $this->keyword($mixinName) &&
                ($this->argumentDef($args) || true) &&
                $this->literal('{')
            ) {
                $mixin = $this->pushSpecialBlock('mixin', $s);
                $mixin->name = $mixinName;
                $mixin->args = $args;

                return true;
            }

            $this->seek($s);

            if ($this->literal('@include') &&
                $this->keyword($mixinName) &&
                ($this->literal('(') &&
                    ($this->argValues($argValues) || true) &&
                    $this->literal(')') || true) &&
                ($this->end() ||
                    $this->literal('{') && $hasBlock = true)
            ) {
                $child = array('include',
                    $mixinName, isset($argValues) ? $argValues : null, null);

                if (! empty($hasBlock)) {
                    $include = $this->pushSpecialBlock('include', $s);
                    $include->child = $child;
                } else {
                    $this->append($child, $s);
                }

                return true;
            }

            $this->seek($s);

            if ($this->literal('@import') &&
                $this->valueList($importPath) &&
                $this->end()
            ) {
                $this->append(array('import', $importPath), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@import') &&
                $this->url($importPath) &&
                $this->end()
            ) {
                $this->append(array('import', $importPath), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@extend') &&
                $this->selectors($selector) &&
                $this->end()
            ) {
                $this->append(array('extend', $selector), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@function') &&
                $this->keyword($fnName) &&
                $this->argumentDef($args) &&
                $this->literal('{')
            ) {
                $func = $this->pushSpecialBlock('function', $s);
                $func->name = $fnName;
                $func->args = $args;

                return true;
            }

            $this->seek($s);

            if ($this->literal('@return') && $this->valueList($retVal) && $this->end()) {
                $this->append(array('return', $retVal), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@each') &&
                $this->genericList($varNames, 'variable', ',', false) &&
                $this->literal('in') &&
                $this->valueList($list) &&
                $this->literal('{')
            ) {
                $each = $this->pushSpecialBlock('each', $s);

                foreach ($varNames[2] as $varName) {
                    $each->vars[] = $varName[1];
                }

                $each->list = $list;

                return true;
            }

            $this->seek($s);

            if ($this->literal('@while') &&
                $this->expression($cond) &&
                $this->literal('{')
            ) {
                $while = $this->pushSpecialBlock('while', $s);
                $while->cond = $cond;

                return true;
            }

            $this->seek($s);

            if ($this->literal('@for') &&
                $this->variable($varName) &&
                $this->literal('from') &&
                $this->expression($start) &&
                ($this->literal('through') ||
                    ($forUntil = true && $this->literal('to'))) &&
                $this->expression($end) &&
                $this->literal('{')
            ) {
                $for = $this->pushSpecialBlock('for', $s);
                $for->var = $varName[1];
                $for->start = $start;
                $for->end = $end;
                $for->until = isset($forUntil);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@if') && $this->valueList($cond) && $this->literal('{')) {
                $if = $this->pushSpecialBlock('if', $s);
                $if->cond = $cond;
                $if->cases = array();

                return true;
            }

            $this->seek($s);

            if ($this->literal('@debug') &&
                $this->valueList($value) &&
                $this->end()
            ) {
                $this->append(array('debug', $value), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@warn') &&
                $this->valueList($value) &&
                $this->end()
            ) {
                $this->append(array('warn', $value), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@error') &&
                $this->valueList($value) &&
                $this->end()
            ) {
                $this->append(array('error', $value), $s);

                return true;
            }

            $this->seek($s);

            if ($this->literal('@content') && $this->end()) {
                $this->append(array('mixin_content'), $s);

                return true;
            }

            $this->seek($s);

            $last = $this->last();

            if (isset($last) && $last[0] === 'if') {
                list(, $if) = $last;

                if ($this->literal('@else')) {
                    if ($this->literal('{')) {
                        $else = $this->pushSpecialBlock('else', $s);
                    } elseif ($this->literal('if') && $this->valueList($cond) && $this->literal('{')) {
                        $else = $this->pushSpecialBlock('elseif', $s);
                        $else->cond = $cond;
                    }

                    if (isset($else)) {
                        $else->dontAppend = true;
                        $if->cases[] = $else;

                        return true;
                    }
                }

                $this->seek($s);
            }

            // only retain the first @charset directive encountered
            if ($this->literal('@charset') &&
                $this->valueList($charset) &&
                $this->end()
            ) {
                if (! isset($this->charset)) {
                    $statement = array('charset', $charset);

                    $statement[self::SOURCE_POSITION] = $s;

                    if (! $this->rootParser) {
                        $statement[self::SOURCE_PARSER] = $this;
                    }

                    $this->charset = $statement;
                }

                return true;
            }

            $this->seek($s);

            // doesn't match built in directive, do generic one
            if ($this->literal('@', false) &&
                $this->keyword($dirName) &&
                ($this->variable($dirValue) || $this->openString('{', $dirValue) || true) &&
                $this->literal('{')
            ) {
                $directive = $this->pushSpecialBlock('directive', $s);
                $directive->name = $dirName;

                if (isset($dirValue)) {
                    $directive->value = $dirValue;
                }

                return true;
            }

            $this->seek($s);

            return false;
        }

        // property shortcut
        // captures most properties before having to parse a selector
        if ($this->keyword($name, false) &&
            $this->literal(': ') &&
            $this->valueList($value) &&
            $this->end()
        ) {
            $name = array('string', '', array($name));
            $this->append(array('assign', $name, $value), $s);

            return true;
        }

        $this->seek($s);

        // variable assigns
        if ($this->variable($name) &&
            $this->literal(':') &&
            $this->valueList($value) &&
            $this->end()
        ) {
            // check for '!flag'
            $assignmentFlag = $this->stripAssignmentFlag($value);
            $this->append(array('assign', $name, $value, $assignmentFlag), $s);

            return true;
        }

        $this->seek($s);

        // misc
        if ($this->literal('-->')) {
            return true;
        }

        // opening css block
        if ($this->selectors($selectors) && $this->literal('{')) {
            $b = $this->pushBlock($selectors, $s);

            return true;
        }

        $this->seek($s);

        // property assign, or nested assign
        if ($this->propertyName($name) && $this->literal(':')) {
            $foundSomething = false;

            if ($this->valueList($value)) {
                $this->append(array('assign', $name, $value), $s);
                $foundSomething = true;
            }

            if ($this->literal('{')) {
                $propBlock = $this->pushSpecialBlock('nestedprop', $s);
                $propBlock->prefix = $name;
                $foundSomething = true;
            } elseif ($foundSomething) {
                $foundSomething = $this->end();
            }

            if ($foundSomething) {
                return true;
            }
        }

        $this->seek($s);

        // closing a block
        if ($this->literal('}')) {
            $block = $this->popBlock();

            if (isset($block->type) && $block->type === 'include') {
                $include = $block->child;
                unset($block->child);
                $include[3] = $block;
                $this->append($include, $s);
            } elseif (empty($block->dontAppend)) {
                $type = isset($block->type) ? $block->type : 'block';
                $this->append(array($type, $block), $s);
            }

            return true;
        }

        // extra stuff
        if ($this->literal(';') ||
            $this->literal('<!--')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Strip assignment flag from the list
     *
     * @param array $value
     *
     * @return string
     */
    protected function stripAssignmentFlag(&$value)
    {
        $token = &$value;

        for ($token = &$value; $token[0] === 'list' && ($s = count($token[2])); $token = &$lastNode) {
            $lastNode = &$token[2][$s - 1];

            if ($lastNode[0] === 'keyword' && in_array($lastNode[1], array('!default', '!global'))) {
                array_pop($token[2]);

                $token = $this->flattenList($token);

                return $lastNode[1];
            }
        }

        return false;
    }

    /**
     * Match literal string
     *
     * @param string  $what
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function literal($what, $eatWhitespace = null)
    {
        if (! isset($eatWhitespace)) {
            $eatWhitespace = $this->eatWhiteDefault;
        }

        // shortcut on single letter
        if (! isset($what[1]) && isset($this->buffer[$this->count])) {
            if ($this->buffer[$this->count] === $what) {
                if (! $eatWhitespace) {
                    $this->count++;

                    return true;
                }
                // goes below...
            } else {
                return false;
            }
        }

        return $this->match($this->pregQuote($what), $m, $eatWhitespace);
    }

    /**
     * Push block onto parse tree
     *
     * @param array   $selectors
     * @param integer $pos
     *
     * @return \stdClass
     */
    protected function pushBlock($selectors, $pos = 0)
    {
        $b = new \stdClass;
        $b->parent = $this->env;

        $b->sourcePosition = $pos;
        $b->sourceParser = $this;
        $b->selectors = $selectors;
        $b->comments = array();

        if (! $this->env) {
            $b->children = array();
        } elseif (empty($this->env->children)) {
            $this->env->children = $this->env->comments;
            $b->children = array();
            $this->env->comments = array();
        } else {
            $b->children = $this->env->comments;
            $this->env->comments = array();
        }

        $this->env = $b;

        return $b;
    }

    /**
     * Push special (named) block onto parse tree
     *
     * @param string  $type
     * @param integer $pos
     *
     * @return \stdClass
     */
    protected function pushSpecialBlock($type, $pos)
    {
        $block = $this->pushBlock(null, $pos);
        $block->type = $type;

        return $block;
    }

    /**
     * Pop scope and return last block
     *
     * @return \stdClass
     *
     * @throws \Exception
     */
    protected function popBlock()
    {
        $block = $this->env;

        if (empty($block->parent)) {
            $this->throwParseError('unexpected }');
        }

        $this->env = $block->parent;
        unset($block->parent);

        $comments = $block->comments;
        if (count($comments)) {
            $this->env->comments = $comments;
            unset($block->comments);
        }

        return $block;
    }

    /**
     * Append comment to current block
     *
     * @param array $comment
     */
    protected function appendComment($comment)
    {
        $comment[1] = substr(preg_replace(array('/^\s+/m', '/^(.)/m'), array('', ' \1'), $comment[1]), 1);

        $this->env->comments[] = $comment;
    }

    /**
     * Append statement to current block
     *
     * @param array   $statement
     * @param integer $pos
     */
    protected function append($statement, $pos = null)
    {
        if ($pos !== null) {
            $statement[self::SOURCE_POSITION] = $pos;

            if (! $this->rootParser) {
                $statement[self::SOURCE_PARSER] = $this;
            }
        }

        $this->env->children[] = $statement;

        $comments = $this->env->comments;

        if (count($comments)) {
            $this->env->children = array_merge($this->env->children, $comments);
            $this->env->comments = array();
        }
    }

    /**
     * Returns last child was appended
     *
     * @return array|null
     */
    protected function last()
    {
        $i = count($this->env->children) - 1;

        if (isset($this->env->children[$i])) {
            return $this->env->children[$i];
        }
    }

    /**
     * Parse media query list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaQueryList(&$out)
    {
        return $this->genericList($out, 'mediaQuery', ',', false);
    }

    /**
     * Parse media query
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaQuery(&$out)
    {
        $s = $this->seek();

        $expressions = null;
        $parts = array();

        if (($this->literal('only') && ($only = true) || $this->literal('not') && ($not = true) || true) &&
            $this->mixedKeyword($mediaType)
        ) {
            $prop = array('mediaType');

            if (isset($only)) {
                $prop[] = array('keyword', 'only');
            }

            if (isset($not)) {
                $prop[] = array('keyword', 'not');
            }

            $media = array('list', '', array());

            foreach ((array)$mediaType as $type) {
                if (is_array($type)) {
                    $media[2][] = $type;
                } else {
                    $media[2][] = array('keyword', $type);
                }
            }

            $prop[] = $media;
            $parts[] = $prop;
        }

        if (empty($parts) || $this->literal('and')) {
            $this->genericList($expressions, 'mediaExpression', 'and', false);

            if (is_array($expressions)) {
                $parts = array_merge($parts, $expressions[2]);
            }
        }

        $out = $parts;

        return true;
    }

    /**
     * Parse media expression
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaExpression(&$out)
    {
        $s = $this->seek();
        $value = null;

        if ($this->literal('(') &&
            $this->expression($feature) &&
            ($this->literal(':') && $this->expression($value) || true) &&
            $this->literal(')')
        ) {
            $out = array('mediaExp', $feature);

            if ($value) {
                $out[] = $value;
            }

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse argument values
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argValues(&$out)
    {
        if ($this->genericList($list, 'argValue', ',', false)) {
            $out = $list[2];

            return true;
        }

        return false;
    }

    /**
     * Parse argument value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argValue(&$out)
    {
        $s = $this->seek();

        $keyword = null;

        if (! $this->variable($keyword) || ! $this->literal(':')) {
            $this->seek($s);
            $keyword = null;
        }

        if ($this->genericList($value, 'expression')) {
            $out = array($keyword, $value, false);
            $s = $this->seek();

            if ($this->literal('...')) {
                $out[2] = true;
            } else {
                $this->seek($s);
            }

            return true;
        }

        return false;
    }

    /**
     * Parse list
     *
     * @param string $out
     *
     * @return boolean
     */
    protected function valueList(&$out)
    {
        return $this->genericList($out, 'spaceList', ',');
    }

    /**
     * Parse space list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function spaceList(&$out)
    {
        return $this->genericList($out, 'expression');
    }

    /**
     * Parse generic list
     *
     * @param array    $out
     * @param callable $parseItem
     * @param string   $delim
     * @param boolean  $flatten
     *
     * @return boolean
     */
    protected function genericList(&$out, $parseItem, $delim = '', $flatten = true)
    {
        $s = $this->seek();
        $items = array();

        while ($this->$parseItem($value)) {
            $items[] = $value;

            if ($delim) {
                if (! $this->literal($delim)) {
                    break;
                }
            }
        }

        if (count($items) === 0) {
            $this->seek($s);

            return false;
        }

        if ($flatten && count($items) === 1) {
            $out = $items[0];
        } else {
            $out = array('list', $delim, $items);
        }

        return true;
    }

    /**
     * Parse expression
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function expression(&$out)
    {
        $s = $this->seek();

        if ($this->literal('(')) {
            if ($this->literal(')')) {
                $out = array('list', '', array());

                return true;
            }

            if ($this->valueList($out) && $this->literal(')') && $out[0] === 'list') {
                return true;
            }

            $this->seek($s);

            if ($this->map($out)) {
                return true;
            }

            $this->seek($s);
        }

        if ($this->value($lhs)) {
            $out = $this->expHelper($lhs, 0);

            return true;
        }

        return false;
    }

    /**
     * Parse left-hand side of subexpression
     *
     * @param array   $lhs
     * @param integer $minP
     *
     * @return array
     */
    protected function expHelper($lhs, $minP)
    {
        $opstr = self::$operatorStr;

        $ss = $this->seek();
        $whiteBefore = isset($this->buffer[$this->count - 1]) &&
            ctype_space($this->buffer[$this->count - 1]);

        while ($this->match($opstr, $m, false) && self::$precedence[$m[1]] >= $minP) {
            $whiteAfter = isset($this->buffer[$this->count]) &&
                ctype_space($this->buffer[$this->count]);
            $varAfter = isset($this->buffer[$this->count]) &&
                $this->buffer[$this->count] === '$';

            $this->whitespace();

            $op = $m[1];

            // don't turn negative numbers into expressions
            if ($op === '-' && $whiteBefore && ! $whiteAfter && ! $varAfter) {
                break;
            }

            if (! $this->value($rhs)) {
                break;
            }

            // peek and see if rhs belongs to next operator
            if ($this->peek($opstr, $next) && self::$precedence[$next[1]] > self::$precedence[$op]) {
                $rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
            }

            $lhs = array('exp', $op, $lhs, $rhs, $this->inParens, $whiteBefore, $whiteAfter);
            $ss = $this->seek();
            $whiteBefore = isset($this->buffer[$this->count - 1]) &&
                ctype_space($this->buffer[$this->count - 1]);
        }

        $this->seek($ss);

        return $lhs;
    }

    /**
     * Parse value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function value(&$out)
    {
        $s = $this->seek();

        if ($this->literal('not', false) && $this->whitespace() && $this->value($inner)) {
            $out = array('unary', 'not', $inner, $this->inParens);

            return true;
        }

        $this->seek($s);

        if ($this->literal('not', false) && $this->parenValue($inner)) {
            $out = array('unary', 'not', $inner, $this->inParens);

            return true;
        }

        $this->seek($s);

        if ($this->literal('+') && $this->value($inner)) {
            $out = array('unary', '+', $inner, $this->inParens);

            return true;
        }

        $this->seek($s);

        // negation
        if ($this->literal('-', false) &&
            ($this->variable($inner) ||
            $this->unit($inner) ||
            $this->parenValue($inner))
        ) {
            $out = array('unary', '-', $inner, $this->inParens);

            return true;
        }

        $this->seek($s);

        if ($this->parenValue($out) ||
            $this->interpolation($out) ||
            $this->variable($out) ||
            $this->color($out) ||
            $this->unit($out) ||
            $this->string($out) ||
            $this->func($out) ||
            $this->progid($out)
        ) {
            return true;
        }

        if ($this->keyword($keyword)) {
            if ($keyword === 'null') {
                $out = array('null');
            } else {
                $out = array('keyword', $keyword);
            }

            return true;
        }

        return false;
    }

    /**
     * Parse parenthesized value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function parenValue(&$out)
    {
        $s = $this->seek();

        $inParens = $this->inParens;

        if ($this->literal('(')) {
            if ($this->literal(')')) {
                $out = array('list', '', array());

                return true;
            }

            $this->inParens = true;

            if ($this->expression($exp) && $this->literal(')')) {
                $out = $exp;
                $this->inParens = $inParens;

                return true;
            }
        }

        $this->inParens = $inParens;
        $this->seek($s);

        return false;
    }

    /**
     * Parse "progid:"
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function progid(&$out)
    {
        $s = $this->seek();

        if ($this->literal('progid:', false) &&
            $this->openString('(', $fn) &&
            $this->literal('(')
        ) {
            $this->openString(')', $args, '(');

            if ($this->literal(')')) {
                $out = array('string', '', array(
                    'progid:', $fn, '(', $args, ')'
                ));

                return true;
            }
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse function call
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function func(&$func)
    {
        $s = $this->seek();

        if ($this->keyword($name, false) &&
            $this->literal('(')
        ) {
            if ($name === 'alpha' && $this->argumentList($args)) {
                $func = array('function', $name, array('string', '', $args));

                return true;
            }

            if ($name !== 'expression' && ! preg_match('/^(-[a-z]+-)?calc$/', $name)) {
                $ss = $this->seek();

                if ($this->argValues($args) && $this->literal(')')) {
                    $func = array('fncall', $name, $args);

                    return true;
                }

                $this->seek($ss);
            }

            if (($this->openString(')', $str, '(') || true ) &&
                $this->literal(')')
            ) {
                $args = array();

                if (! empty($str)) {
                    $args[] = array(null, array('string', '', array($str)));
                }

                $func = array('fncall', $name, $args);

                return true;
            }
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse function call argument list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argumentList(&$out)
    {
        $s = $this->seek();
        $this->literal('(');

        $args = array();

        while ($this->keyword($var)) {
            $ss = $this->seek();

            if ($this->literal('=') && $this->expression($exp)) {
                $args[] = array('string', '', array($var . '='));
                $arg = $exp;
            } else {
                break;
            }

            $args[] = $arg;

            if (! $this->literal(',')) {
                break;
            }

            $args[] = array('string', '', array(', '));
        }

        if (! $this->literal(')') || ! count($args)) {
            $this->seek($s);

            return false;
        }

        $out = $args;

        return true;
    }

    /**
     * Parse mixin/function definition  argument list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argumentDef(&$out)
    {
        $s = $this->seek();
        $this->literal('(');

        $args = array();

        while ($this->variable($var)) {
            $arg = array($var[1], null, false);

            $ss = $this->seek();

            if ($this->literal(':') && $this->genericList($defaultVal, 'expression')) {
                $arg[1] = $defaultVal;
            } else {
                $this->seek($ss);
            }

            $ss = $this->seek();

            if ($this->literal('...')) {
                $sss = $this->seek();

                if (! $this->literal(')')) {
                    $this->throwParseError('... has to be after the final argument');
                }

                $arg[2] = true;
                $this->seek($sss);
            } else {
                $this->seek($ss);
            }

            $args[] = $arg;

            if (! $this->literal(',')) {
                break;
            }
        }

        if (! $this->literal(')')) {
            $this->seek($s);

            return false;
        }

        $out = $args;

        return true;
    }

    /**
     * Parse map
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function map(&$out)
    {
        $s = $this->seek();

        if (! $this->literal('(')) {
            return false;
        }

        $keys = array();
        $values = array();

        while ($this->genericList($key, 'expression') && $this->literal(':') &&
            $this->genericList($value, 'expression')
        ) {
            $keys[] = $key;
            $values[] = $value;

            if (! $this->literal(',')) {
                break;
            }
        }

        if (! count($keys) || ! $this->literal(')')) {
            $this->seek($s);

            return false;
        }

        $out = array('map', $keys, $values);

        return true;
    }

    /**
     * Parse color
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function color(&$out)
    {
        $color = array('color');

        if ($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
            if (isset($m[3])) {
                $num = $m[3];
                $width = 16;
            } else {
                $num = $m[2];
                $width = 256;
            }

            $num = hexdec($num);

            foreach (array(3, 2, 1) as $i) {
                $t = $num % $width;
                $num /= $width;

                $color[$i] = $t * (256/$width) + $t * floor(16/$width);
            }

            $out = $color;

            return true;
        }

        return false;
    }

    /**
     * Parse number with unit
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function unit(&$unit)
    {
        if ($this->match('([0-9]*(\.)?[0-9]+)([%a-zA-Z]+)?', $m)) {
            $unit = array('number', $m[1], empty($m[3]) ? '' : $m[3]);

            return true;
        }

        return false;
    }

    /**
     * Parse string
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function string(&$out)
    {
        $s = $this->seek();

        if ($this->literal('"', false)) {
            $delim = '"';
        } elseif ($this->literal('\'', false)) {
            $delim = '\'';
        } else {
            return false;
        }

        $content = array();
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        while ($this->matchString($m, $delim)) {
            $content[] = $m[1];

            if ($m[2] === '#{') {
                $this->count -= strlen($m[2]);

                if ($this->interpolation($inter, false)) {
                    $content[] = $inter;
                } else {
                    $this->count += strlen($m[2]);
                    $content[] = '#{'; // ignore it
                }
            } elseif ($m[2] === '\\') {
                $content[] = $m[2];

                if ($this->literal($delim, false)) {
                    $content[] = $delim;
                }
            } else {
                $this->count -= strlen($delim);
                break; // delim
            }
        }

        $this->eatWhiteDefault = $oldWhite;

        if ($this->literal($delim)) {
            $out = array('string', $delim, $content);

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse keyword or interpolation
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mixedKeyword(&$out)
    {
        $s = $this->seek();

        $parts = array();

        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        for (;;) {
            if ($this->keyword($key)) {
                $parts[] = $key;
                continue;
            }

            if ($this->interpolation($inter)) {
                $parts[] = $inter;
                continue;
            }

            break;
        }

        $this->eatWhiteDefault = $oldWhite;

        if (count($parts) === 0) {
            return false;
        }

        if ($this->eatWhiteDefault) {
            $this->whitespace();
        }

        $out = $parts;

        return true;
    }

    /**
     * Parse an unbounded string stopped by $end
     *
     * @param string $end
     * @param array  $out
     * @param string $nestingOpen
     *
     * @return boolean
     */
    protected function openString($end, &$out, $nestingOpen = null)
    {
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        $stop = array('\'', '"', '#{', $end);
        $stop = array_map(array($this, 'pregQuote'), $stop);
        $stop[] = self::$commentMulti;

        $patt = '(.*?)(' . implode('|', $stop) . ')';

        $nestingLevel = 0;

        $content = array();

        while ($this->match($patt, $m, false)) {
            if (isset($m[1]) && $m[1] !== '') {
                $content[] = $m[1];

                if ($nestingOpen) {
                    $nestingLevel += substr_count($m[1], $nestingOpen);
                }
            }

            $tok = $m[2];

            $this->count-= strlen($tok);

            if ($tok === $end && ! $nestingLevel--) {
                break;
            }

            if (($tok === '\'' || $tok === '"') && $this->string($str)) {
                $content[] = $str;
                continue;
            }

            if ($tok === '#{' && $this->interpolation($inter)) {
                $content[] = $inter;
                continue;
            }

            $content[] = $tok;
            $this->count+= strlen($tok);
        }

        $this->eatWhiteDefault = $oldWhite;

        if (count($content) === 0) {
            return false;
        }

        // trim the end
        if (is_string(end($content))) {
            $content[count($content) - 1] = rtrim(end($content));
        }

        $out = array('string', '', $content);

        return true;
    }

    /**
     * Parser interpolation
     *
     * @param array   $out
     * @param boolean $lookWhite save information about whitespace before and after
     *
     * @return boolean
     */
    protected function interpolation(&$out, $lookWhite = true)
    {
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = true;

        $s = $this->seek();

        if ($this->literal('#{') && $this->valueList($value) && $this->literal('}', false)) {
            // TODO: don't error if out of bounds

            if ($lookWhite) {
                $left = preg_match('/\s/', $this->buffer[$s - 1]) ? ' ' : '';
                $right = preg_match('/\s/', $this->buffer[$this->count]) ? ' ': '';
            } else {
                $left = $right = false;
            }

            $out = array('interpolate', $value, $left, $right);
            $this->eatWhiteDefault = $oldWhite;

            if ($this->eatWhiteDefault) {
                $this->whitespace();
            }
            return true;
        }

        $this->seek($s);
        $this->eatWhiteDefault = $oldWhite;
        return false;
    }

    /**
     * Parse property name (as an array of parts or a string)
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function propertyName(&$out)
    {
        $s = $this->seek();
        $parts = array();

        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        for (;;) {
            if ($this->interpolation($inter)) {
                $parts[] = $inter;
            } elseif ($this->keyword($text)) {
                $parts[] = $text;
            } elseif (count($parts) === 0 && $this->match('[:.#]', $m, false)) {
                // css hacks
                $parts[] = $m[0];
            } else {
                break;
            }
        }

        $this->eatWhiteDefault = $oldWhite;

        if (count($parts) === 0) {
            return false;
        }

        // match comment hack
        if (preg_match(
            self::$whitePattern,
            $this->buffer,
            $m,
            null,
            $this->count
        )) {
            if (! empty($m[0])) {
                $parts[] = $m[0];
                $this->count += strlen($m[0]);
            }
        }

        $this->whitespace(); // get any extra whitespace

        $out = array('string', '', $parts);

        return true;
    }

    /**
     * Parse comma separated selector list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selectors(&$out)
    {
        $s = $this->seek();
        $selectors = array();

        while ($this->selector($sel)) {
            $selectors[] = $sel;

            if (! $this->literal(',')) {
                break;
            }

            while ($this->literal(',')) {
                ; // ignore extra
            }
        }

        if (count($selectors) === 0) {
            $this->seek($s);

            return false;
        }

        $out = $selectors;

        return true;
    }

    /**
     * Parse whitespace separated selector list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selector(&$out)
    {
        $selector = array();

        for (;;) {
            if ($this->match('[>+~]+', $m)) {
                $selector[] = array($m[0]);
            } elseif ($this->selectorSingle($part)) {
                $selector[] = $part;
                $this->match('\s+', $m);
            } elseif ($this->match('\/[^\/]+\/', $m)) {
                $selector[] = array($m[0]);
            } else {
                break;
            }

        }

        if (count($selector) === 0) {
            return false;
        }

        $out = $selector;
        return true;
    }

    /**
     * Parse the parts that make up a selector
     *
     * {@internal
     *     div[yes=no]#something.hello.world:nth-child(-2n+1)%placeholder
     * }}
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selectorSingle(&$out)
    {
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        $parts = array();

        if ($this->literal('*', false)) {
            $parts[] = '*';
        }

        for (;;) {
            // see if we can stop early
            if ($this->match('\s*[{,]', $m)) {
                $this->count--;
                break;
            }

            $s = $this->seek();

            // self
            if ($this->literal('&', false)) {
                $parts[] = Compiler::$selfSelector;
                continue;
            }

            if ($this->literal('.', false)) {
                $parts[] = '.';
                continue;
            }

            if ($this->literal('|', false)) {
                $parts[] = '|';
                continue;
            }

            if ($this->match('\\\\\S', $m)) {
                $parts[] = $m[0];
                continue;
            }

            // for keyframes
            if ($this->unit($unit)) {
                $parts[] = $unit;
                continue;
            }

            if ($this->keyword($name)) {
                $parts[] = $name;
                continue;
            }

            if ($this->interpolation($inter)) {
                $parts[] = $inter;
                continue;
            }

            if ($this->literal('%', false) && $this->placeholder($placeholder)) {
                $parts[] = '%';
                $parts[] = $placeholder;
                continue;
            }

            if ($this->literal('#', false)) {
                $parts[] = '#';
                continue;
            }

            // a pseudo selector
            if ($this->match('::?', $m) && $this->mixedKeyword($nameParts)) {
                $parts[] = $m[0];

                foreach ($nameParts as $sub) {
                    $parts[] = $sub;
                }

                $ss = $this->seek();

                if ($this->literal('(') &&
                    ($this->openString(')', $str, '(') || true ) &&
                    $this->literal(')')
                ) {
                    $parts[] = '(';

                    if (! empty($str)) {
                        $parts[] = $str;
                    }

                    $parts[] = ')';
                } else {
                    $this->seek($ss);
                }

                continue;
            }

            $this->seek($s);

            // attribute selector
            // TODO: replace with open string?
            if ($this->literal('[', false)) {
                $attrParts = array('[');

                // keyword, string, operator
                for (;;) {
                    if ($this->literal(']', false)) {
                        $this->count--;
                        break; // get out early
                    }

                    if ($this->match('\s+', $m)) {
                        $attrParts[] = ' ';
                        continue;
                    }

                    if ($this->string($str)) {
                        $attrParts[] = $str;
                        continue;
                    }

                    if ($this->keyword($word)) {
                        $attrParts[] = $word;
                        continue;
                    }

                    if ($this->interpolation($inter, false)) {
                        $attrParts[] = $inter;
                        continue;
                    }

                    // operator, handles attr namespace too
                    if ($this->match('[|-~\$\*\^=]+', $m)) {
                        $attrParts[] = $m[0];
                        continue;
                    }

                    break;
                }

                if ($this->literal(']', false)) {
                    $attrParts[] = ']';

                    foreach ($attrParts as $part) {
                        $parts[] = $part;
                    }

                    continue;
                }

                $this->seek($s);
                // TODO: should just break here?
            }

            break;
        }

        $this->eatWhiteDefault = $oldWhite;

        if (count($parts) === 0) {
            return false;
        }

        $out = $parts;

        return true;
    }

    /**
     * Parse a variable
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function variable(&$out)
    {
        $s = $this->seek();

        if ($this->literal('$', false) && $this->keyword($name)) {
            $out = array('var', $name);

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse a keyword
     *
     * @param string  $word
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function keyword(&$word, $eatWhitespace = null)
    {
        if ($this->match(
            '(([\w_\-\*!"\']|[\\\\].)([\w\-_"\']|[\\\\].)*)',
            $m,
            $eatWhitespace
        )) {
            $word = $m[1];

            return true;
        }

        return false;
    }

    /**
     * Parse a placeholder
     *
     * @param string $placeholder
     *
     * @return boolean
     */
    protected function placeholder(&$placeholder)
    {
        if ($this->match('([\w\-_]+|#[{][$][\w\-_]+[}])', $m)) {
            $placeholder = $m[1];

            return true;
        }

        return false;
    }

    /**
     * Parse a url
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function url(&$out)
    {
        if ($this->match('(url\(\s*(["\']?)([^)]+)\2\s*\))', $m)) {
            $out = array('string', '', array('url(' . $m[2] . $m[3] . $m[2] . ')'));

            return true;
        }

        return false;
    }

    /**
     * Consume an end of statement delimiter
     *
     * @return boolean
     */
    protected function end()
    {
        if ($this->literal(';')) {
            return true;
        }

        if ($this->count === strlen($this->buffer) || $this->buffer[$this->count] === '}') {
            // if there is end of file or a closing block next then we don't need a ;
            return true;
        }

        return false;
    }

    /**
     * @deprecated
     *
     * {@internal
     *     advance counter to next occurrence of $what
     *     $until - don't include $what in advance
     *     $allowNewline, if string, will be used as valid char set
     * }}
     */
    protected function to($what, &$out, $until = false, $allowNewline = false)
    {
        if (is_string($allowNewline)) {
            $validChars = $allowNewline;
        } else {
            $validChars = $allowNewline ? '.' : "[^\n]";
        }

        if (! $this->match('(' . $validChars . '*?)' . $this->pregQuote($what), $m, ! $until)) {
            return false;
        }

        if ($until) {
            $this->count -= strlen($what); // give back $what
        }

        $out = $m[1];

        return true;
    }

    /**
     * Throw parser error
     *
     * @param string  $msg
     * @param integer $count
     *
     * @throws \Exception
     */
    public function throwParseError($msg = 'parse error', $count = null)
    {
        $count = ! isset($count) ? $this->count : $count;

        $line = $this->getLineNo($count);

        if (! empty($this->sourceName)) {
            $loc = "$this->sourceName on line $line";
        } else {
            $loc = "line: $line";
        }

        if ($this->peek("(.*?)(\n|$)", $m, $count)) {
            throw new \Exception("$msg: failed at `$m[1]` $loc");
        }

        throw new \Exception("$msg: $loc");
    }

    /**
     * Get source file name
     *
     * @return string
     */
    public function getSourceName()
    {
        return $this->sourceName;
    }

    /**
     * Get source line number (given character position in the buffer)
     *
     * @param integer $pos
     *
     * @return integer
     */
    public function getLineNo($pos)
    {
        return 1 + substr_count(substr($this->buffer, 0, $pos), "\n");
    }

    /**
     * Match string looking for either ending delim, escape, or string interpolation
     *
     * {@internal This is a workaround for preg_match's 250K string match limit. }}
     *
     * @param array  $m     Matches (passed by reference)
     * @param string $delim Delimeter
     *
     * @return boolean True if match; false otherwise
     */
    protected function matchString(&$m, $delim)
    {
        $token = null;

        $end = strlen($this->buffer);

        // look for either ending delim, escape, or string interpolation
        foreach (array('#{', '\\', $delim) as $lookahead) {
            $pos = strpos($this->buffer, $lookahead, $this->count);

            if ($pos !== false && $pos < $end) {
                $end = $pos;
                $token = $lookahead;
            }
        }

        if (! isset($token)) {
            return false;
        }

        $match = substr($this->buffer, $this->count, $end - $this->count);
        $m = array(
            $match . $token,
            $match,
            $token
        );
        $this->count = $end + strlen($token);

        return true;
    }

    /**
     * Try to match something on head of buffer
     *
     * @param string  $regex
     * @param array   $out
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function match($regex, &$out, $eatWhitespace = null)
    {
        if (! isset($eatWhitespace)) {
            $eatWhitespace = $this->eatWhiteDefault;
        }

        $r = '/' . $regex . '/Ais';

        if (preg_match($r, $this->buffer, $out, null, $this->count)) {
            $this->count += strlen($out[0]);

            if ($eatWhitespace) {
                $this->whitespace();
            }

            return true;
        }

        return false;
    }

    /**
     * Match some whitespace
     *
     * @return boolean
     */
    protected function whitespace()
    {
        $gotWhite = false;

        while (preg_match(self::$whitePattern, $this->buffer, $m, null, $this->count)) {
            if (isset($m[1]) && empty($this->commentsSeen[$this->count])) {
                $this->appendComment(array('comment', $m[1]));

                $this->commentsSeen[$this->count] = true;
            }

            $this->count += strlen($m[0]);
            $gotWhite = true;
        }

        return $gotWhite;
    }

    /**
     * Peek input stream
     *
     * @param string  $regex
     * @param array   $out
     * @param integer $from
     *
     * @return integer
     */
    protected function peek($regex, &$out, $from = null)
    {
        if (! isset($from)) {
            $from = $this->count;
        }

        $r = '/' . $regex . '/Ais';
        $result = preg_match($r, $this->buffer, $out, null, $from);

        return $result;
    }

    /**
     * Seek to position in input stream (or return current position in input stream)
     *
     * @param integer $where
     *
     * @return integer
     */
    protected function seek($where = null)
    {
        if ($where === null) {
            return $this->count;
        }

        $this->count = $where;

        return true;
    }

    /**
     * Quote regular expression
     *
     * @param string $what
     *
     * @return string
     */
    public static function pregQuote($what)
    {
        return preg_quote($what, '/');
    }

    /**
     * @deprecated
     */
    protected function show()
    {
        if ($this->peek("(.*?)(\n|$)", $m, $this->count)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Turn list of length 1 into value type
     *
     * @param array $value
     *
     * @return array
     */
    protected function flattenList($value)
    {
        if ($value[0] === 'list' && count($value[2]) === 1) {
            return $this->flattenList($value[2][0]);
        }

        return $value;
    }
}
