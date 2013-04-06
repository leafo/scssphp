<?php

require_once __DIR__ . "/../scss.inc.php";

class ExceptionTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->scss = new scssc();
	}

	public function compile($str) {
		return trim($this->scss->compile($str));
	}

	public function testMixinUnusedArgs() {
		try {
			$output = $this->compile(<<<END_OF_SCSS
@mixin do-nothing() {
}

.test {
  @include do-nothing(\$a: "hello");
}
END_OF_SCSS
			);
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'Mixin or function doesn\'t have an argument named $a.') !== false) {
				return;
			};
		}

		$this->fail('Expected exception to be raised');
	}
}
