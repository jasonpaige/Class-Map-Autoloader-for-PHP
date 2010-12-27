<?php

/**
 * @desc AutoLoader
 * @author jason
 */
class AutoLoader {

    protected $rootDirectory;
    protected $classMap = array();
    protected $reloadClassMap;
    protected $fileExt = array(".php" => true, ".php4" => true, ".php5" => true, ".mphp" => true, ".phpm" => true);
    protected static $instance;
    protected $cacheFile = "/.classmapcache";

    /**
     *
     * @param string $rootDirectory
     * @param boolean $reloadClassMap
     * @param array $fileExt
     */
    protected function __construct($rootDirectory = null, $reloadClassMap = true, $fileExt = null) {
        $this->rootDirectory = $rootDirectory != null ? $rootDirectory : dirname(__FILE__);
        $this->reloadClassMap = $reloadClassMap;
        $this->fileExt = $fileExt != null && is_array($fileExt) ? $fileExt : $this->fileExt;

        spl_autoload_register("AutoLoader::autoload");
            
        $this->init();
    }

    protected function init() {
        try {
            $this->loadFromCache();
        } catch (Exception $ex) {
            $this->rebuild();
        }
    }

    /**
     * Returns the instance of the AutoLoader Singleton or instantiates a new one
     * @return AutoLoader
     */
    public static function instance($rootDirectory = null, $reloadClassMap = true, $fileExt = null) {
        if (self::$instance == null) {
            self::$instance = new AutoLoader($rootDirectory, $reloadClassMap, $fileExt);
        }
        return self::$instance;
    }

    /**
     * rebuild the class map
     */
    public function rebuild() {
        if ($this->reloadClassMap) {
            $this->mapFilesInDir($this->rootDirectory);
            $this->saveToCache();
        } else {
            throw new Exception("Unable to rebuild class map", 101);
        }
    }

    public function getCacheLocation() {
        return $this->rootDirectory.$this->cacheFile;
    }

    protected function loadFromCache() {
        try {
            if (!$serializedData = file_get_contents($this->rootDirectory.$this->cacheFile)) {
                throw new Exception("Unable to load cache file {$this->rootDirectory}{$this->cacheFile}");
            }
        } catch (Exception $ex) {
            throw new Exception("Unable to load cache file {$this->rootDirectory}{$this->cacheFile}");
        }
        $this->classMap = unserialize($serializedData);
    }

    /**
     * serializes the classMap array and saves ti to the cacheFile
     */
    public function saveToCache() {
        $data = serialize($this->classMap);
        $fp = fopen($this->rootDirectory.$this->cacheFile, 'w');
        fwrite($fp, $data);
        fclose($fp);
    }

    /**
     * expires the cache
     */
    public function expireCache() {
        try {
            unlink($this->rootDirectory.$this->cacheFile);
        } catch (Exception $ex) {
            // file didn't exist so it doesn't matter.
        }
    }

    /**
     * Scan a directory for file with an accepted extension and add them to the map.
     * @param string $dir
     */
    protected function mapFilesInDir($dir) {
        $handle = opendir($dir);
        while (($file = readdir($handle)) !== false) {
            $ext = ".".pathinfo($dir.$file , PATHINFO_EXTENSION);
            if ($this->fileExt[$ext] !== true) {
                continue;
            }
            $filepath = $dir == '.' ? $file : $dir . '/' . $file;
            if (is_link($filepath)) {
                continue;
            }
            if (is_file($filepath)) {
                $this->loadClassesFromFile($filepath);
            } else if (is_dir($filepath)) {
                $this->mapFilesInDir($filepath);
            }
        }
        closedir($handle);
    }

    /**
     * Scans a file for classes
     * @todo Add namespace support
     * @param string $filepath
     */
    protected function loadClassesFromFile($filepath) {
        $sourceCode = file_get_contents($filepath);
        $tokens = token_get_all($sourceCode);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if ($tokens[$i - 2][0] == T_CLASS &&
                $tokens[$i - 1][0] == T_WHITESPACE &&
                $tokens[$i][0] == T_STRING) {

                $class_name = $tokens[$i][1];
                $this->classMap[$class_name] = $filepath;
            }
        }
    }

    /**
     * Attempts to include the requested class name if it doesn't already exist
     * and is in our class map
     * @todo look at PHP5.3 and namespace issues...
     * @param string $className
     * @return boolean
     */
    public function includeClass($className) {
        if (class_exists($className)) {
            return true;
        }
        if (isset($this->classMap[$className])) {
            try {
                include $this->classMap[$className];
                return true;
            }
            catch (FrameEx $ex) { /*drop below and return false */ }
        }

        return false;
    }

    /**
     * This function is registered with the SPL
     * @param string $className
     */
    public static function autoload($className) {
        if (self::instance()->includeClass($className) === false) {
            try {
                self::instance()->rebuild();
                self::instance()->includeClass($className);
            } catch (Exception $ex) {
                if ($ex->getCode() == 101) {
                    throw new Exception("Unable to load class {$className}, class not found in map and \$reloadClassMap is set to false.");
                } else {
                    throw new Exception("Unable to load class {$className}, even after trying to rebuild map.");
                }
            }
        }
    }

    /**
     * Registers a function to be called when this object is to be saved into a cache
     * Note: If using < PHP5.3 it will assume $function is a function name
     * @param function|string $function
     */
    public function registerCacheFunction($function) {
        $this->cacheFunction = $function;
    }
}
