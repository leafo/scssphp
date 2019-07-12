<?php
/**
 * SCSS File Watcher for PHP Storm
 * @copyright 2014 Peter Culka
 */
 
/** 
 * SCSS console script which can be used as a PHPStorm Watcher for parsing input scss files
 * You need to have PHP installed! No ruby shit needed, just plain PHP for PHP projects ;)
 * usage in PHP storm - add a file watcher
 * Program: {path_to_php}/php (can be php.exe)
 * Arguments: {path_to_consolegen.php}/consolegen.php --infile=$FileName$ --outfile=$FileNameWithoutExtension$.css --workdir=$FileDir$
 *
 * rest is default
 */

date_default_timezone_set('Europe/Vienna');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "scss.inc.php";

// parse input arguments 
$shortOpts = '';
$longOpts  = array
(
	"infile:",     // Required value
	"outfile:",    // Optional value
	"workdir:",
	"test:"
);
$options = getopt($shortOpts, $longOpts);
if ( strlen($options['infile']) == 0 || strlen($options['outfile']) == 0 || strlen($options['workdir']) == 0 )
{
	die('Sorry, some arguments are missing, be sure that --infile= --outfile= and --workdir= options are being passed to this script');
}
// maybe do some more checks

$scss = new scssc();
$scss->setFormatter("scss_formatter_compressed");
$fh = fopen($options['workdir'].DIRECTORY_SEPARATOR.$options['outfile'], 'w');
fwrite($fh, $scss->compile(preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', file_get_contents($options['workdir'].DIRECTORY_SEPARATOR.$options['infile']))));
fclose($fh);

?>
