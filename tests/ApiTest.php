<?php

require_once __DIR__ . "/../scss.inc.php";

class ApiTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->scss = new scssc();
	}

	public function testUserFunction() {
		$this->scss->registerFunction("add-two", function($args) {
			list($a, $b) = $args;
			return $a[1] + $b[1];
		});

		$this->assertEquals(
			$this->compile("result: add-two(10, 20);"),
			"result: 30;");
	}

	public function compile($str) {
		return trim($this->scss->compile($str));
	}

}
