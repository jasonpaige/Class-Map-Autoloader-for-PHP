<?php
/**
 * Rebuilds the class mapping cache file for the auto_loader
 * This can be done at run time but is real slow for big projects
 * and definately doesn't want to be run in production so this
 * should be run as part of deployment
 * @example php ./rebuild_class_map.php
 * @author Jason Paige
 */
if(php_sapi_name() != 'cli') {
    die("This script can only be run on the command line.");
}

define("ROOT_PATH", __DIR__ . DIRECTORY_SEPARATOR . "..");

require_once ROOT_PATH . "/AutoLoader.php";

$autoLoader = AutoLoader::instance(ROOT_PATH, true);
$autoLoader->expireCache();
$autoLoader->ignore(ROOT_PATH . "ignore_folder")
           ->ignore(ROOT_PATH . "ignore_folder2");
$autoLoader->init();

// correct the generated file paths
$classMap = file_get_contents($autoLoader->getCacheLocation());
if (!file_put_contents($autoLoader->getCacheLocation(), $classMap)) {
    echo "Unable to write class map cache!";
    exit(1);
} else {
    echo "New class map cache generated at " . $autoLoader->getCacheLocation()."\n";
    exit;
}