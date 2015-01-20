<?php

namespace Leafo\ScssPhp\Tests;

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\LineCommentator;

// Runs all the tests in inputs/ and compares their output to ouputs/

function _dump($value)
{
    fwrite(STDOUT, print_r($value, true));
}

function _quote($str)
{
    return preg_quote($str, '/');
}

class InputTest extends \PHPUnit_Framework_TestCase
{
    protected static $inputDir = 'inputs';
    protected static $outputDir = 'outputs';

    protected static $line_number_suffix = '_numbered';
    protected static $numbered_folder = 'numbered';


    public function setUp()
    {
        $this->scss = new Compiler();
        $this->scss->addImportPath(__DIR__ . '/' . self::$inputDir);
    }

    /**
     * @dataProvider fileNameProvider
     */
    public function testInputFile($inFname, $outFname)
    {

        if (getenv('BUILD')) {
            return $this->buildInput($inFname, $outFname);
        }

        if (!is_readable($outFname)) {
            $this->fail("$outFname is missing, consider building tests with BUILD=true");
        }


        $input = file_get_contents($inFname);
        $output = file_get_contents($outFname);

        $this->assertEquals($output, $this->scss->compile($input));
    }

    /*
     * run all tests with line numbering
     */

    /**
     * @dataProvider fileNameProvider
     */

    public function testLineNumbering($inFname, $outFname) {


        $outPath = self::lineNumberPath($outFname);
        $inPath = __DIR__ . '/'.self::$inputDir.'/'.self::$numbered_folder.'/'.self::fileName($inFname);

        //write scss
        $scss = LineCommentator::insertLineComments(file($inFname),  self::$numbered_folder.'/'.self::fileName($inFname));
        file_put_contents($inPath, $scss);

        if (getenv('BUILD')) {

            //write css
            $css = $this->scss->compile($scss);
            file_put_contents($outPath, $css);
        }

        if (!is_readable($outPath)) {
            $this->fail("$outPath is missing, consider building tests with BUILD=true");
        }


        $input = file_get_contents($inPath);
        $output = file_get_contents($outPath);

        $this->assertEquals($output, $this->scss->compile($input));


    }

    public function fileNameProvider()
    {
        return array_map(
            function ($a) {
                return array($a, InputTest::outputNameFor($a));
            },
            self::findInputNames()
        );
    }

    // only run when env is set
    public function buildInput($inFname, $outFname)
    {
        $css = $this->scss->compile(file_get_contents($inFname));
        file_put_contents($outFname, $css);
    }

    public static function findInputNames($pattern = '*')
    {
        $files = glob(__DIR__ . '/' . self::$inputDir . '/' . $pattern);
        $files = array_filter($files, 'is_file');
        if ($pattern = getenv('MATCH')) {
            $files = array_filter($files, function ($fname) use ($pattern) {
                return preg_match("/$pattern/", $fname);
            });
        }

        return $files;
    }

    public static function outputNameFor($input)
    {
        $front = _quote(__DIR__ . '/');
        $out = preg_replace("/^$front/", '', $input);

        $in = _quote(self::$inputDir . '/');
        $out = preg_replace("/$in/", self::$outputDir . '/', $out);
        $out = preg_replace("/.scss$/", '.css', $out);

        return __DIR__ . '/' . $out;
    }

    public static function buildTests($pattern)
    {
        $files = self::findInputNames($pattern);

        foreach ($files as $file) {
        }
    }

    public static function lineNumberPath($outFname) {

        $outFname = preg_replace("/.css$/", self::$line_number_suffix.'.css', self::fileName($outFname));

        return __DIR__ .'/'.self::$outputDir . '/' . $outFname;
    }

    public static function fileName($path) {

        $filename = explode('/',$path);
        return end($filename);
    }
}
