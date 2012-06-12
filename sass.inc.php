<?php

class sassc {
	function compile($code, $name=null) {
		$this->indentLevel = -1;

		$parser = new scss_parser($name);
		$tree = $parser->parse($code);
		$this->formatter = new sass_formatter();

		return $this->compileBlock($tree);
	}

	protected function compileBlock($block) {
		$idelta = $this->formatter->indentAmount($block);
		$this->indentLevel += $idelta;

		$lines = array();
		$children = array();
		foreach ($block->children as $child) {
			$this->compileChild($child, $lines, $children);
		}

		$this->indentLevel -= $idelta;

		return $this->formatter->block($block->selectors, false,
			$lines, $children, $this->indentLevel);
	}

	protected function compileChild($child, &$lines, &$children) {
		list($type) = $child;
		switch ($type) {
		case "block":
			$children[] = $this->compileBlock($child[1]);
			break;
		case "assign":
			$lines[] = $child[1] . ":" . $this->compileValue($child[2]);
			break;
		default:
			throw new exception("unknown type: $type");
		}
	}

	protected function compileValue($value) {
		list($type) = $value;
		switch ($type) {
		case "keyword":
			return $value[1];
		}
	}
}


class scss_parser {
	function __construct($sourceName = null) {
		$this->sourceName = $sourceName;
	}

	function parse($buffer) {
		$this->count = 0;
		$this->line = 1;
		$this->env = null;
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
			$this->append(array("block", $this->popBlock()));
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

	protected function popBlock() {
		$old = $this->env;
		$this->env = $this->env->parent;
		unset($old->parent);
		return $old;
	}

	protected function append($statement) {
		$this->env->children[] = $statement;
	}

	// high level parsers (they returns parts of ast)

	protected function assign(&$out) {
		$s = $this->seek();
		if ($this->keyword($name) && $this->literal(":") && $this->value($value)) {
			$out = array("assign", $name, $value);
			return true;
		}

		$this->seek($s);
		return false;
	}

	protected function value(&$out) {
		if ($this->keyword($keyword)) {
			$out = array("keyword", $keyword);
			return true;
		}

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

	// consume a keyword
	protected function keyword(&$word) {
		if ($this->match('([\w_\-\*!"][\w\-_"]*)', $m)) {
			$word = $m[1];
			return true;
		}
		return false;
	}

	// consume an end of statement delimiter
	protected function end() {
		if ($this->literal(';'))
			return true;
		elseif ($this->count == strlen($this->buffer) || $this->buffer{$this->count} == '}') {
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
		if (!$this->match('('.$validChars.'*?)'.lessc::preg_quote($what), $m, !$until)) return false;
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


