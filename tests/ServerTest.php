<?php

class ServerTest extends \PHPUnit_Framework_TestCase
{
	public function testCheckedCachedCompile()
	{
		$server = new Leafo\ScssPhp\Server(__DIR__ . '/inputs/');
		$css = $server->checkedCachedCompile(__DIR__ . '/inputs/import.scss', '/tmp/scss.css');

		$this->assertFileExists('/tmp/scss.css');
		$this->assertFileExists('/tmp/scss.css.meta');
		$this->assertEquals($css, file_get_contents('/tmp/scss.css'));
		$this->assertNotNull(unserialize(file_get_contents('/tmp/scss.css.meta')));
	}
}
