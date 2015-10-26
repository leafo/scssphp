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
use Leafo\ScssPhp\Version;

/**
 * SCSS server
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class Server
{
    /**
     * @var boolean
     */
    private $showErrorsAsCSS;

    /**
     * @var string
     */
    private $dir;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var \Leafo\ScssPhp\Compiler
     */
    private $scss;

    /**
     * Join path components
     *
     * @param string $left  Path component, left of the directory separator
     * @param string $right Path component, right of the directory separator
     *
     * @return string
     */
    protected function join($left, $right)
    {
        return rtrim($left, '/\\') . DIRECTORY_SEPARATOR . ltrim($right, '/\\');
    }

    /**
     * Get name of requested .scss file
     *
     * @return string|null
     */
    protected function inputName()
    {
        switch (true) {
            case isset($_GET['p']):
                return $_GET['p'];
            case isset($_SERVER['PATH_INFO']):
                return $_SERVER['PATH_INFO'];
            case isset($_SERVER['DOCUMENT_URI']):
                return substr($_SERVER['DOCUMENT_URI'], strlen($_SERVER['SCRIPT_NAME']));
        }
    }

    /**
     * Get path to requested .scss file
     *
     * @return string
     */
    protected function findInput()
    {
        if (($input = $this->inputName())
            && strpos($input, '..') === false
            && substr($input, -5) === '.scss'
        ) {
            $name = $this->join($this->dir, $input);

            if (is_file($name) && is_readable($name)) {
                return $name;
            }
        }

        return false;
    }

    /**
     * Get path to cached .css file
     *
     * @return string
     */
    protected function cacheName($fname)
    {
        return $this->join($this->cacheDir, md5($fname) . '.css');
    }

    /**
     * Get path to meta data
     *
     * @return string
     */
    protected function metadataName($out)
    {
        return $out . '.meta';
    }

    /**
     * Determine whether .scss file needs to be re-compiled.
     *
     * @param string $in   Input path
     * @param string $out  Output path
     * @param string $etag ETag
     *
     * @return boolean True if compile required.
     */
    protected function needsCompile($in, $out, &$etag)
    {
        if (! is_file($out)) {
            return true;
        }

        $mtime = filemtime($out);

        $metadataName = $this->metadataName($out);

        if (is_readable($metadataName)) {
            $metadata = unserialize(file_get_contents($metadataName));

            foreach ($metadata['imports'] as $import => $originalMtime) {
                $currentMtime = filemtime($import);

                if ($currentMtime !== $originalMtime || $currentMtime > $mtime) {
                    return true;
                }
            }

            $metaVars = crc32(serialize($this->scss->getVariables()));

            if ($metaVars !== $metadata['vars']) {
                return true;
            }

            $etag = $metadata['etag'];

            return false;
        }

        return true;
    }

    /**
     * Get If-Modified-Since header from client request
     *
     * @return string|null
     */
    protected function getIfModifiedSinceHeader()
    {
        $modifiedSince = null;

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $modifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'];

            if (false !== ($semicolonPos = strpos($modifiedSince, ';'))) {
                $modifiedSince = substr($modifiedSince, 0, $semicolonPos);
            }
        }

        return $modifiedSince;
    }

    /**
     * Get If-None-Match header from client request
     *
     * @return string|null
     */
    protected function getIfNoneMatchHeader()
    {
        $noneMatch = null;

        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $noneMatch = $_SERVER['HTTP_IF_NONE_MATCH'];
        }

        return $noneMatch;
    }

    /**
     * Compile .scss file
     *
     * @param string $in  Input path (.scss)
     * @param string $out Output path (.css)
     *
     * @return array
     */
    protected function compile($in, $out)
    {
        $start   = microtime(true);
        $css     = $this->scss->compile(file_get_contents($in), $in);
        $elapsed = round((microtime(true) - $start), 4);

        $v    = Version::VERSION;
        $t    = @date('r');
        $css  = "/* compiled by scssphp $v on $t (${elapsed}s) */\n\n" . $css;
        $etag = md5($css);

        file_put_contents($out, $css);
        file_put_contents(
            $this->metadataName($out),
            serialize(array(
                'etag'    => $etag,
                'imports' => $this->scss->getParsedFiles(),
                'vars'    => crc32(serialize($this->scss->getVariables())),
            ))
        );

        return array($css, $etag);
    }

    /**
     * Format error as a pseudo-element in CSS
     *
     * @param \Exception $error
     *
     * @return string
     */
    protected function createErrorCSS($error)
    {
        $message = str_replace(
            array("'", "\n"),
            array("\\'", "\\A"),
            $error->getfile() . ":\n\n" . $error->getMessage()
        );

        return "body { display: none !important; }
                html:after {
                    background: white;
                    color: black;
                    content: '$message';
                    display: block !important;
                    font-family: mono;
                    padding: 1em;
                    white-space: pre;
                }";
    }

    /**
     * Render errors as a pseudo-element within valid CSS, displaying the errors on any
     * page that includes this CSS.
     *
     * @param boolean $show
     */
    public function showErrorsAsCSS($show = true)
    {
        $this->showErrorsAsCSS = $show;
    }

    /**
     * Compile .scss file
     *
     * @param string $in  Input file (.scss)
     * @param string $out Output file (.css) optional
     *
     * @return string|bool
     */
    public function compileFile($in, $out = null)
    {
        if (! is_readable($in)) {
            throw new \Exception('load error: failed to find ' . $in);
        }

        $pi = pathinfo($in);

        $this->scss->addImportPath($pi['dirname'] . '/');

        $compiled = $this->scss->compile(file_get_contents($in), $in);

        if ($out !== null) {
            return file_put_contents($out, $compiled);
        }

        return $compiled;
    }

    /**
     * Check if file need compiling
     *
     * @param string $in  Input file (.scss)
     * @param string $out Output file (.css)
     *
     * @return bool
     */
    public function checkedCompile($in, $out)
    {
        if (! is_file($out) || filemtime($in) > filemtime($out)) {
            $this->compileFile($in, $out);

            return true;
        }

        return false;
    }

    /**
     * Compile requested scss and serve css.  Outputs HTTP response.
     *
     * @param string $salt Prefix a string to the filename for creating the cache name hash
     */
    public function serve($salt = '')
    {
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? $_SERVER['SERVER_PROTOCOL']
            : 'HTTP/1.0';

        if ($input = $this->findInput()) {
            $output = $this->cacheName($salt . $input);
            $etag = $noneMatch = trim($this->getIfNoneMatchHeader(), '"');

            if ($this->needsCompile($input, $output, $etag)) {
                try {
                    list($css, $etag) = $this->compile($input, $output);

                    $lastModified = gmdate('D, d M Y H:i:s', filemtime($output)) . ' GMT';

                    header('Last-Modified: ' . $lastModified);
                    header('Content-type: text/css');
                    header('ETag: "' . $etag . '"');

                    echo $css;

                } catch (\Exception $e) {
                    if ($this->showErrorsAsCSS) {
                        header('Content-type: text/css');

                        echo $this->createErrorCSS($e);
                    } else {
                        header($protocol . ' 500 Internal Server Error');
                        header('Content-type: text/plain');

                        echo 'Parse error: ' . $e->getMessage() . "\n";
                    }

                }

                return;
            }

            header('X-SCSS-Cache: true');
            header('Content-type: text/css');
            header('ETag: "' . $etag . '"');

            if ($etag === $noneMatch) {
                header($protocol . ' 304 Not Modified');

                return;
            }

            $modifiedSince = $this->getIfModifiedSinceHeader();
            $mtime = filemtime($output);

            if (@strtotime($modifiedSince) === $mtime) {
                header($protocol . ' 304 Not Modified');

                return;
            }

            $lastModified  = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
            header('Last-Modified: ' . $lastModified);

            echo file_get_contents($output);

            return;
        }

        header($protocol . ' 404 Not Found');
        header('Content-type: text/plain');

        $v = Version::VERSION;
        echo "/* INPUT NOT FOUND scss $v */\n";
    }

    /**
     * Based on explicit input/output files does a full change check on cache before compiling.
     *
     * @param string  $in
     * @param string  $out
     * @param boolean $force
     *
     * @return string Compiled CSS results
     *
     * @throws \Exception
     */
    public function checkedCachedCompile($in, $out, $force = false)
    {
        if (! is_file($in) || ! is_readable($in)) {
            throw new \Exception('Invalid or unreadable input file specified.');
        }

        if (is_dir($out) || ! is_writable(file_exists($out) ? $out : dirname($out))) {
            throw new \Exception('Invalid or unwritable output file specified.');
        }

        if ($force || $this->needsCompile($in, $out, $etag)) {
            list($css, $etag) = $this->compile($in, $out);
        } else {
            $css = file_get_contents($out);
        }

        return $css;
    }

    /**
     * Constructor
     *
     * @param string                       $dir      Root directory to .scss files
     * @param string                       $cacheDir Cache directory
     * @param \Leafo\ScssPhp\Compiler|null $scss     SCSS compiler instance
     */
    public function __construct($dir, $cacheDir = null, $scss = null)
    {
        $this->dir = $dir;

        if (! isset($cacheDir)) {
            $cacheDir = $this->join($dir, 'scss_cache');
        }

        $this->cacheDir = $cacheDir;

        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        if (! isset($scss)) {
            $scss = new Compiler();
            $scss->setImportPaths($this->dir);
        }

        $this->scss = $scss;
        $this->showErrorsAsCSS = false;
    }

    /**
     * Helper method to serve compiled scss
     *
     * @param string $path Root path
     */
    public static function serveFrom($path)
    {
        $server = new self($path);
        $server->serve();
    }
}
