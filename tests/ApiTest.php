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
			"result: 30;",
			$this->compile("result: add-two(10, 20);"));
	}
	
	public function testImportMissing(){
		$this->assertEquals(
			'@import "missing";',
			$this->compile('@import "missing";'));
	}
	
	public function testImportCustomCallback(){
		$this->scss->addImportPath(function($path) {
			return __DIR__.'/inputs/' . str_replace('.css','.scss',$path);
		});
		
		$this->assertEquals(
			trim(file_get_contents(__DIR__.'/outputs/variables.css')),
			$this->compile('@import "variables.css";'));
	}

	public function compile($str) {
		return trim($this->scss->compile($str));
	}

}
