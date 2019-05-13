<?php
/**
 * SCSSPHP
 *
 * @copyright 2012-2018 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.github.io/scssphp
 */

namespace Leafo\ScssPhp;

use Exception;

/**
 * The scss cache manager.
 *
 * In short:
 *
 * allow to put in cache/get from fache a generic result from a known operation on a generic dataset,
 * taking in account options that affects the result
 *
 * The cache manager is agnostic about data format and only the operation is expected to be descripted by string
 *
 */

/**
 * SCSS cache
 *
 * @author Cedric Morin
 */
class Cache
{

    const CACHE_VERSION = 0;


    // directory used for storing data
    public static $cache_dir = false;

    // prefix for the storing data
    public static $prefix = 'scssphp_';

    // force a refresh : 'once' for refreshing the first hit on a cache only, true to never use the cache in this hit
    public static $force_refresh = false;

    // specifies the number of seconds after which data cached will be seen as 'garbage' and potentially cleaned up
    public static $gc_lifetime = 604800;


    // array of already refreshed cache if $force_refresh==='once'
    protected static $refreshed = [];


    /**
     * Constructor
     */
    public function __construct($options)
    {
        //check $cache_dir
        if (isset($options['cache_dir'])) {
            self::$cache_dir = $options['cache_dir'];
        }

        if (empty(self::$cache_dir)) {
            throw new Exception('cache_dir not set');
        }

        if (isset($options['prefix'])) {
            self::$prefix = $options['prefix'];
        }

        if (empty(self::$prefix)) {
            throw new Exception('prefix not set');
        }

        if (isset($options['force_refresh'])) {
            self::$force_refresh = $options['force_refresh'];
        }

        self::checkCacheDir();
    }


    /**
     * Get the cached result of $operation on $what, which is known as dependant from the content of $options
     *
     * @param string $operation
     *   parse, compile...
     * @param $what
     *  content key (filename to be treated for instance)
     * @param array $options
     *  any option that affect the operation result on the content
     * @param int $last_modified
     * @return mixed
     * @throws Exception
     */
    public function getCache($operation, $what, $options = array(), $last_modified = null)
    {

        $fileCache = self::$cache_dir . self::cacheName($operation, $what, $options);

        if ((! self::$force_refresh || (self::$force_refresh === 'once' && isset(self::$refreshed[$fileCache])))
          and file_exists($fileCache)) {
            $cache_time = filemtime($fileCache);
            if ((is_null($last_modified) or $cache_time > $last_modified)
              and $cache_time + self::$gc_lifetime > time()) {
                $c = file_get_contents($fileCache);
                $c = unserialize($c);
                if (is_array($c) and isset($c['value'])) {
                    return $c['value'];
                }
            }
        }

        return null;
    }

    /**
     * Put in cache the result of $operation on $what, which is known as dependant from the content of $options
     *
     * @param string $operation
     * @param $what
     * @param $value
     * @param array $options
     */
    public function setCache($operation, $what, $value, $options = array())
    {
        $fileCache = self::$cache_dir . self::cacheName($operation, $what, $options);

        $c = array('value' => $value);
        $c = serialize($c);
        file_put_contents($fileCache, $c);

        if (self::$force_refresh === 'once') {
            self::$refreshed[$fileCache] = true;
        }
    }


    /**
     * get the cachename for the caching of $opetation on $what, which is known as dependant from the content of $options
     * @param string $operation
     * @param $what
     * @param array $options
     * @return string
     */
    private static function cacheName($operation, $what, $options = array())
    {

        $t = array(
          'version' => self::CACHE_VERSION,
          'operation' => $operation,
          'what' => $what,
          'options' => $options
        );

        $t = self::$prefix
          . sha1(json_encode($t))
          . ".$operation"
          . ".scsscache";

        return $t;
    }


    /**
     * Check that the cache dir is existing and writeable
     * @throws Exception
     */
    public static function checkCacheDir()
    {

        self::$cache_dir = str_replace('\\', '/', self::$cache_dir);
        self::$cache_dir = rtrim(self::$cache_dir, '/') . '/';

        if (! file_exists(self::$cache_dir)) {
            if (! mkdir(self::$cache_dir)) {
                throw new Exception('Cache directory couldn\'t be created: ' . self::$cache_dir);
            }
        } elseif (! is_dir(self::$cache_dir)) {
            throw new Exception('Cache directory doesn\'t exist: ' . self::$cache_dir);
        } elseif (! is_writable(self::$cache_dir)) {
            throw new Exception('Cache directory isn\'t writable: ' . self::$cache_dir);
        }
    }

    /**
     * Delete unused cached files
     *
     */
    public static function cleanCache()
    {
        static $clean = false;


        if ($clean || empty(self::$cache_dir)) {
            return;
        }

        $clean = true;

        // only remove files with extensions created by SCSSPHP Cache
        // css files removed based on the list files
        $remove_types = array('scsscache' => 1);

        $files = scandir(self::$cache_dir);
        if (! $files) {
            return;
        }

        $check_time = time() - self::$gc_lifetime;
        foreach ($files as $file) {
            // don't delete if the file wasn't created with SCSSPHP Cache
            if (strpos($file, self::$prefix) !== 0) {
                continue;
            }

            $parts = explode('.', $file);
            $type = array_pop($parts);


            if (! isset($remove_types[$type])) {
                continue;
            }

            $full_path = self::$cache_dir . $file;
            $mtime = filemtime($full_path);

            // don't delete if it's a relatively new file
            if ($mtime > $check_time) {
                continue;
            }

            unlink($full_path);
        }
    }
}
