<?php

require_once __DIR__ . "/../scss.inc.php";

class ExceptionTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->scss = new scssc();
	}

	/**
	 * @param string $scss
	 * @param string $expectedExceptionMessage
	 *
	 * @dataProvider provideScss
	 */
	public function testThrowError($scss, $expectedExceptionMessage) {
		try {
			$this->compile($scss);
		} catch (Exception $e) {
			if (strpos($e->getMessage(), $expectedExceptionMessage) !== false) {
				return;
			};
		}

		$this->fail('Expected exception to be raised: ' . $expectedExceptionMessage);
	}

	/**
	 * @return array
	 */
	public function provideScss() {
		return array(
			array(<<<END_OF_SCSS
.test {
  @include foo();
}
END_OF_SCSS
,
				'Undefined mixin foo'
			),
			array(<<<END_OF_SCSS
@mixin do-nothing() {
}

.test {
  @include do-nothing(\$a: "hello");
}
END_OF_SCSS
,
				'Mixin or function doesn\'t have an argument named $a.'
			),
			array(<<<END_OF_SCSS
div {
  color: darken(cobaltgreen, 10%);
}
END_OF_SCSS
,
				'expecting color'
			),
		);
	}

	private function compile($str) {
		return trim($this->scss->compile($str));
	}
}
