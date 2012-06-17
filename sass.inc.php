<?php

class sassc {
	static protected $operatorNames = array(
		'+' => "add",
		'-' => "sub",
		'*' => "mul",
		'/' => "div",
		'%' => "mod",
	);

	static protected $namespaces = array(
		"mixin" => "@",
		"function" => "^",
	);

	function compile($code, $name=null) {
		$this->indentLevel = -1;

		$parser = new scss_parser($name);
		$tree = $parser->parse($code);
		$this->formatter = new sass_formatter();

		$this->env = null;
		// print_r($tree);
		return $this->compileBlock($tree);
	}

	protected function compileBlock($block) {
		$this->pushEnv($block);
		$idelta = $this->formatter->indentAmount($block);
		$this->indentLevel += $idelta;

		$lines = array();
		$children = array();
		foreach ($block->children as $child) {
			$this->compileChild($child, $lines, $children);
		}

		$this->indentLevel -= $idelta;

		$selectors = $this->multiplySelectors();
		$this->popEnv();
		return $this->formatter->block($selectors, false,
			$lines, $children, $this->indentLevel);
	}

	protected function compileChild($child, &$lines, &$children) {
		switch ($child[0]) {
		case "block":
			$children[] = $this->compileBlock($child[1]);
			break;
		case "assign":
			list(,$name, $value) = $child;
			if (is_array($name)) {
				// setting a variable
				$this->set($name[1], $this->reduce($value));
			} else {
				$lines[] = $child[1] . ":" . $this->compileValue($child[2]) . ";";
			}
			break;
		case "mixin":
		case "function":
			list(,$block) = $child;
			$this->set(self::$namespaces[$block->type] . $block->name, $block);
			break;
		case "include": // including a mixin
			list(,$name) = $child;
			$mixin = $this->get(self::$namespaces["mixin"] . $name);
			foreach ($mixin->children as $child) {
				$this->compileChild($child, $lines, $children);
			}
			break;
		default:
			throw new exception("unknown child type: $child[0]");
		}
	}

	protected function reduce($value, $inExp = false) {
		list($type) = $value;
		switch ($type) {
			case "exp":
				list(, $op, $left, $right, $inParens) = $value;
				$opName = self::$operatorNames[$op];

				// only do division in special cases
				// TODO: add variables type check here
				if ($opName == "div" && !$inParens && !$inExp) {
					return array("keyword", $this->compileValue($left) . "/" . $this->compileValue($right));
				}

				$left = $this->reduce($left, true);
				$right = $this->reduce($right, true);

				$fn = "op_${opName}_${left[0]}_${right[0]}";
				if (is_callable(array($this, $fn))) {
					return $this->$fn($left, $right);
				}
				// echo "missing fn: $fn\n";
				// remember the whitespace around the operator to recreate it?
				return array("list", "", array($left, array("keyword", $op), $right));
			case "var":
				list(, $name) = $value;
				return $this->reduce($this->get($name));
			case "function":
				list(,$name, $args) = $value;
				// user defined function?
				$func = $this->get(self::$namespaces["function"] . $name);
				if ($func) {
					print_r($func);
					$this->pushEnv();

					// ignore any lines or children
					$lines = array();
					$children = array();
					foreach ($func->children as $child) {
						$this->compileChild($child, $lines, $children);
					}

					$ret = isset($func->returns) ?
						$this->reduce($func->returns) : array("keyword", "");

					$this->popEnv();
					return $ret;
				}

				return $value;
			default:
				return $value;
		}
	}

	protected function op_add_number_number($left, $right) {
		return array("number", $left[1] + $right[1], "");
	}

	protected function op_mul_number_number($left, $right) {
		return array("number", $left[1] * $right[1], "");
	}

	protected function op_sub_number_number($left, $right) {
		return array("number", $left[1] - $right[1], "");
	}

	protected function op_div_number_number($left, $right) {
		return array("number", $left[1] / $right[1], "");
	}

	protected function op_mod_number_number($left, $right) {
		return array("number", $left[1] % $right[1], "");
	}

	protected function compileValue($value) {
		$value = $this->reduce($value);

		list($type) = $value;
		switch ($type) {
		case "keyword":
			return $value[1];
		case "color":
			// [1] - red component (either number for a %)
			// [2] - green component
			// [3] - blue component
			// [4] - optional alpha component
			list(, $r, $g, $b) = $value;
			$r = round($r);
			$g = round($g);
			$b = round($b);

			if (count($value) == 5 && $value[4] != 1) { // rgba
				return 'rgba('.$r.','.$g.','.$b.','.$value[4].')';
			}

			$h = sprintf("#%02x%02x%02x", $r, $g, $b);

			// Converting hex color to short notation (e.g. #003399 to #039)
			if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
				$h = '#' . $h[1] . $h[3] . $h[5];
			}

			return $h;
		case "number":
			return $value[1] . $value[2];
		case "string":
			return $value[1] . $this->compileStringContent($value) . $value[1];
		case "function":
			$args = !empty($value[2]) ? $this->compileValue($value[2]) : "";
			return "$value[1]($args)";
		case "list":
			$value = $this->extractInterpolation($value);
			if ($value[0] != "list") return $this->compileValue($value);

			list(, $delim, $items) = $value;
			foreach ($items as &$item) {
				$item = $this->compileValue($item);
			}
			return implode("$delim ", $items);
		case "interpolated": # node created by extractInterpolation
			list(, $interpolate, $left, $right) = $value;
			list(,, $white_left, $white_right) = $interpolate;

			$left = count($left[2]) > 0 ?
				$this->compileValue($left).$white_left : "";

			$right = count($right[2]) > 0 ?
				$white_right.$this->compileValue($right) : "";

			return $left.$this->compileValue($interpolate).$right;

		case "interpolate": # raw parse node
			list(, $exp) = $value;

			// strip quotes if it's a string
			$reduced = $this->reduce($exp);
			if ($reduced[0] == "string") {
				$reduced = array("keyword",
					$this->compileStringContent($reduced));
			}

			return $this->compileValue($reduced);
		default:
			throw new exception("unknown value type: $type");
		}
	}

	protected function compileStringContent($string) {
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

	// doesn't need to be recursive, compileValue will handle that
	protected function extractInterpolation($list) {
		$items = $list[2];
		foreach ($items as $i => $item) {
			if ($item[0] == "interpolate") {
				$before = array("list", $list[1], array_slice($items, 0, $i));
				$after = array("list", $list[1], array_slice($items, $i + 1));
				return array("interpolated", $item, $before, $after);
			}
		}
		return $list;
	}

	// find the final set of selectors
	protected function multiplySelectors($env = null, $childSelectors = null) {
		if (is_null($env)) $env = $this->env;
		$block = $env->block;

		if (is_null($childSelectors)) {
			$selectors = $block->selectors;
		} else {
			$selectors = array();
			foreach ($block->selectors as $parent) {
				foreach ($childSelectors as $child) {
					$selectors[] = $parent . " " . $child;
				}
			}
		}

		if (!empty($env->parent->parent)) { // non root environment
			return $this->multiplySelectors($env->parent, $selectors);
		} else {
			return $selectors;
		}
	}

	// we have environments in addition to blocks for handling mixins, where
	// blocks are reused in different contexts
	protected function pushEnv($block=null) {
		$env = new stdclass;
		$env->parent = $this->env;
		$env->store = array();
		$env->block = $block;

		$this->env = $env;
		return $env;
	}

	protected function set($name, $value) {
		$this->env->store[$name] = $value;
	}

	protected function get($name, $env=null) {
		if (is_null($env)) $env = $this->env;

		if (isset($env->store[$name])) {
			return $env->store[$name];
		} elseif (!is_null($env->parent)) {
			return $this->get($name, $env->parent);
		}

		return array("keyword", ""); // found nothing
	}

	protected function popEnv() {
		$env = $this->env;
		$this->env = $this->env->parent;
		return $env;
	}
}


class scss_parser {
	static protected $precedence = array(
		'+' => 1,
		'-' => 1,
		'*' => 2,
		'/' => 2,
		'%' => 2,
	);

	static protected $operators = array("+", "-", "*", "/", "%");
	static protected $operatorStr;

	function __construct($sourceName = null) {
		$this->sourceName = $sourceName;

		if (empty(scss_parser::$operatorStr)) {
			self::$operatorStr = $this->makeOperatorStr(self::$operators);
		}
	}

	static protected function makeOperatorStr($operators) {
		return '('.implode('|', array_map(array('scss_parser','preg_quote'),
			$operators)).')';
	}

	function parse($buffer) {
		$this->count = 0;
		$this->line = 1;
		$this->env = null;
		$this->inParens = false;
		$this->pushBlock(null); // root block

		$this->buffer = $this->removeComments($buffer);

		// trim whitespace on head
		if (preg_match('/^\s+/', $this->buffer, $m)) {
			$this->line += substr_count($m[0], "\n");
			$this->buffer = ltrim($this->buffer);
		}

		while (false !== $this->parseChunk());

		if ($this->count != strlen($this->buffer))
			$this->throwParseError();

		$this->env->isRoot = true;
		return $this->env;
	}

	protected function parseChunk() {
		$s = $this->seek();

		// the directives
		if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] == "@") {
			if ($this->literal("@mixin") && $this->keyword($mixin_name) && $this->literal("{")) {
				$mixin = $this->pushSpecialBlock("mixin");
				$mixin->name = $mixin_name;
				return true;
			} else {
				$this->seek($s);
			}

			if ($this->literal("@include") && $this->keyword($mixin_name) && $this->end()) {
				$this->append(array("include", $mixin_name));
				return true;
			} else {
				$this->seek($s);
			}

			if ($this->literal("@function") &&
				$this->keyword($fn_name) &&
				$this->argumentDef($args) &&
				$this->literal("{"))
			{
				$func = $this->pushSpecialBlock("function");
				$func->name = $fn_name;
				$func->args = $args;
				return true;
			} else {
				$this->seek($s);
			}

			if ($this->literal("@return") && $this->valueList($ret_val) && $this->end()) {
				$this->env->returns = $ret_val;
				return true;
			} else {
				$this->seek($s);
			}
		}

		// assign
		if ($this->assign($assign) && $this->end()) {
			$this->append($assign);
			return true;
		} else {
			$this->seek($s);
		}

		// opening css block
		if ($this->selectors($selectors) && $this->literal("{")) {
			$this->pushBlock($selectors);
			return true;
		} else {
			$this->seek($s);
		}

		// closing a block
		if ($this->literal("}")) {
			$block = $this->popBlock();
			$type = isset($block->type) ? $block->type : "block";
			$this->append(array($type, $block));
			return true;
		}

		return false;
	}

	protected function literal($what, $eatWhitespace = true) {
		// this is here mainly prevent notice from { } string accessor
		if ($this->count >= strlen($this->buffer)) return false;

		// shortcut on single letter
		if (!$eatWhitespace && strlen($what) == 1) {
			if ($this->buffer{$this->count} == $what) {
				$this->count++;
				return true;
			}
			else return false;
		}

		return $this->match($this->preg_quote($what), $m, $eatWhitespace);
	}

	// tree builders

	protected function pushBlock($selectors) {
		$b = new stdclass;
		$b->parent = $this->env; // not sure if we need this yet

		$b->selectors = $selectors;
		$b->children = array();

		$this->env = $b;
		return $b;
	}

	protected function pushSpecialBlock($type) {
		$block = $this->pushBlock(null);
		$block->type = $type;
		return $block;
	}

	protected function popBlock() {
		if (is_null($this->env->parent)) {
			$this->throwParseError("unexpected }");
		}

		$old = $this->env;
		$this->env = $this->env->parent;
		unset($old->parent);
		return $old;
	}

	protected function append($statement) {
		$this->env->children[] = $statement;
	}

	// high level parsers (they return parts of ast)

	protected function assign(&$out) {
		$s = $this->seek();
		if (($this->keyword($name) || $this->variable($name)) &&
			$this->literal(":") && $this->valueList($value))
		{
			$out = array("assign", $name, $value);
			return true;
		}

		$this->seek($s);
		return false;
	}

	protected function valueList(&$out) {
		if ($this->genericList($list, "commaList")) {
			$out = $list;
			return true;
		}

		return false;
	}

	protected function commaList(&$out) {
		return $this->genericList($out, "expression", ",");
	}

	protected function genericList(&$out, $parseItem, $delim="") {
		$s = $this->seek();
		$items = array();
		while ($this->$parseItem($value)) {
			$items[] = $value;
			if ($delim) {
				if (!$this->literal($delim)) break;
			}
		}

		if (count($items) == 0) {
			$this->seek($s);
			return false;
		}

		if (count($items) == 1) {
			$out = $items[0];
		} else {
			$out = array("list", $delim, $items);
		}

		return true;
	}

	protected function expression(&$out) {
		if ($this->value($lhs)) {
			$out = $this->expHelper($lhs, 0);
			return true;
		}
		return false;
	}

	protected function expHelper($lhs, $minP) {
		$opstr = self::$operatorStr;

		$ss = $this->seek();
		while ($this->match($opstr, $m) && self::$precedence[$m[1]] >= $minP) {
			$op = $m[1];

			if (!$this->value($rhs)) break;

			// peek and see if rhs belongs to next operator
			if ($this->peek($opstr, $next) && self::$precedence[$next[1]] > self::$precedence[$op]) {
				$rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
			}

			$lhs = array("exp", $op, $lhs, $rhs, $this->inParens);
			$ss = $this->seek();
		}

		$this->seek($ss);
		return $lhs;
	}

	protected function value(&$out) {
		$s = $this->seek();

		// parens
		$inParens = $this->inParens;
		if ($this->literal("(") &&
			($this->inParens = true) && $this->expression($exp) &&
			$this->literal(")"))
		{
			$out = $exp;
			$this->inParens = $inParens;
			return true;
		} else {
			$this->inParens = $inParens;
			$this->seek($s);
		}

		if ($this->interpolation($out)) return true;
		if ($this->variable($out)) return true;
		if ($this->color($out)) return true;
		if ($this->unit($out)) return true;
		if ($this->string($out)) return true;
		if ($this->func($out)) return true;

		// convert keyword to be more borad and include numbers/symbols
		if ($this->keyword($keyword)) {
			$out = array("keyword", $keyword);
			return true;
		}


		return false;
	}

	protected function func(&$func) {
		$s = $this->seek();

		if ($this->keyword($name, false) &&
			$this->literal("(") &&
			($this->valueList($args) || true) &&
			$this->literal(")"))
		{
			$func = array("function", $name, $args);
			return true;
		}

		$this->seek($s);
		return false;
	}

	protected function argumentDef(&$out) {
		$s = $this->seek();
		$this->literal("(");

		$args = array();
		while ($this->variable($var)) {
			$args[] = $var;
			if (!$this->literal(",")) break;
		}

		if (!$this->literal(")")) {
			$this->seek($s);
			return false;
		}

		$out = $args;
		return true;
	}

	protected function color(&$out) {
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
			foreach (array(3,2,1) as $i) {
				$t = $num % $width;
				$num /= $width;

				$color[$i] = $t * (256/$width) + $t * floor(16/$width);
			}

			$out = $color;
			return true;
		}

		return false;
	}

	protected function unit(&$unit) {
		if ($this->match('(-?[0-9]*(\.)?[0-9]+)([%a-zA-Z]+)?', $m)) {
			$unit = array("number", $m[1], empty($m[3]) ? "" : $m[3]);
			return true;
		}
		return false;
	}

	protected function string(&$out) {
		$s = $this->seek();
		if ($this->literal('"', false)) {
			$delim = '"';
		} elseif ($this->literal("'", false)) {
			$delim = "'";
		} else {
			return false;
		}

		$content = array();

		// look for either ending delim or string interpolation
		$patt = '([^\n]*?)('.
			$this->preg_quote("#{").'|'. $this->preg_quote($delim).')';

		while ($this->match($patt, $m)) {
			$content[] = $m[1];
			if ($m[2] == "#{") {
				$ss = $this->seek();
				if ($this->valueList($value) && $this->literal("}", false)) {
					$content[] = array("interpolate", $value);
				} else {
					$this->seek($ss);
					$content[] = "#{"; // ignore it
				}
			} else {
				break; // delim
			}
		}

		$out = array("string", $delim, $content);
		return true;
	}

	// where should this be parsed?
	protected function interpolation(&$out) {
		$s = $this->seek();
		if ($this->literal("#{") && $this->valueList($value) && $this->literal("}", false)) {

			// TODO: don't error if out of bounds
			$left = preg_match('/\s/', $this->buffer[$s - 1]) ? " " : "";
			$right = preg_match('/\s/', $this->buffer[$this->count]) ? " ": "";

			// get rid of the whitespace we didn't get before
			$this->match("", $m);

			$out = array("interpolate", $value, $left, $right);
			return true;
		}

		$this->seek($s);
		return false;
	}

	// low level parsers

	protected function selectors(&$out) {
		$s = $this->seek();
		$selectors = array();
		while ($this->selector($sel)) {
			$selectors[] = $sel;
			if (!$this->literal(",")) break;
		}

		if (count($selectors) == 0) {
			$this->seek($s);
			return false;
		}

		$out = $selectors;
		return true;
	}

	public function selector(&$out) {
		return $this->keyword($out);
	}

	protected function variable(&$out) {
		$s = $this->seek();
		if ($this->literal("$", false) && $this->keyword($name)) {
			$out = array("var", $name);
			return true;
		}
		$this->seek($s);
		return false;
	}

	protected function keyword(&$word, $eatWhitespace=true) {
		if ($this->match('([\w_\-\*!"][\w\-_"]*)', $m, $eatWhitespace)) {
			$word = $m[1];
			return true;
		}
		return false;
	}

	// consume an end of statement delimiter
	protected function end() {
		if ($this->literal(';')) {
			return true;
		} elseif ($this->count == strlen($this->buffer) || $this->buffer{$this->count} == '}') {
			// if there is end of file or a closing block next then we don't need a ;
			return true;
		}
		return false;
	}

	// advance counter to next occurrence of $what
	// $until - don't include $what in advance
	// $allowNewline, if string, will be used as valid char set
	protected function to($what, &$out, $until = false, $allowNewline = false) {
		if (is_string($allowNewline)) {
			$validChars = $allowNewline;
		} else {
			$validChars = $allowNewline ? "." : "[^\n]";
		}
		if (!$this->match('('.$validChars.'*?)'.$this->preg_quote($what), $m, !$until)) return false;
		if ($until) $this->count -= strlen($what); // give back $what
		$out = $m[1];
		return true;
	}

	protected function throwParseError($msg = "parse error", $count = null) {
		$count = is_null($count) ? $this->count : $count;

		$line = $this->line +
			substr_count(substr($this->buffer, 0, $count), "\n");

		if (!empty($this->sourceName)) {
			$loc = "$this->sourceName on line $line";
		} else {
			$loc = "line: $line";
		}

		if ($this->peek("(.*?)(\n|$)", $m, $count)) {
			throw new exception("$msg: failed at `$m[1]` $loc");
		} else {
			throw new exception("$msg: $loc");
		}
	}

	// try to match something on head of buffer
	protected function match($regex, &$out, $eatWhitespace = true) {
		$r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
			return true;
		}
		return false;
	}

	protected function peek($regex, &$out, $from=null) {
		if (is_null($from)) $from = $this->count;

		$r = '/'.$regex.'/Ais';
		$result = preg_match($r, $this->buffer, $out, null, $from);

		return $result;
	}

	protected function seek($where = null) {
		if ($where === null) return $this->count;
		else $this->count = $where;
		return true;
	}

	protected function removeComments($buffer) {
		return $buffer; // TODO: this
	}

	static function preg_quote($what) {
		return preg_quote($what, '/');
	}
}

class sass_formatter {
	public $indentChar = "  ";

	public $break = "\n";
	public $open = " {";
	public $close = "}";
	public $tagSeparator = ", ";

	public $disableSingle = false;
	public $openSingle = " { ";
	public $closeSingle = " }";

	// returns the amount of indent that should happen for a block
	function indentAmount($block) {
		return isset($block->isRoot) || !empty($block->no_multiply) ? 1 : 0;
	}

	// an $indentLevel of -1 signifies the root level
	function block($tags, $wrapChildren, $lines, $children, $indentLevel) {
		$indent = str_repeat($this->indentChar, max($indentLevel, 0));

		// what $lines is imploded by
		$nl = $indentLevel == -1 ? $this->break :
			$this->break.$indent.$this->indentChar;

		ob_start();

		$isSingle = !$this->disableSingle && !$wrapChildren
			&& count($lines) <= 1;

		$showDelim = !empty($tags) && (count($lines) > 0 || $wrapChildren);

		if ($showDelim) {
			if (is_array($tags)) {
				$tags = implode($this->tagSeparator, $tags);
			}

			echo $indent.$tags;
			if ($isSingle) echo $this->openSingle;
			else {
				echo $this->open;
				if (!empty($lines)) echo $nl;
				else echo $this->break;
			}
		}

		echo implode($nl, $lines);

		if ($wrapChildren) {
			if (!empty($lines)) echo $this->break;
			foreach ($children as $child) echo $child;
		}

		if ($showDelim) {
			if ($isSingle) echo $this->closeSingle;
			else {
				if (!$wrapChildren) echo $this->break;
				echo $indent.$this->close;
			}
			echo $this->break;
		} elseif (!empty($lines)) {
			echo $this->break;
		}

		if (!$wrapChildren)
			foreach ($children as $child) echo $child;

		return ob_get_clean();
	}

	function property($name, $values) {
		return "";
	}
}



$data = "";
while (!feof(STDIN)) {
	$data .= fread(STDIN, 8192);
}

if ($data) {
	$sass = new sassc();
	echo $sass->compile($data);
}


