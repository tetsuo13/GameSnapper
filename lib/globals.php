<?php
/**
 * Constants and other things all scripts need.
 *
 * @copyright 2010-2011 GameSnapper
 * @since     2010-11-18
 * @author    Andrei Nicholson
 */

$serverName = (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : NULL);

define('DEBUG_MODE',
       ($serverName !== NULL ? (substr($serverName, 0, 3) != 'www') : FALSE));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

date_default_timezone_set('America/New_York');

/** Absolute path to base directory. */
define('ROOT_DIR', realpath(dirname(__FILE__) . '/..') . '/');

/** Absolute path to PHP library files. */
define('LIB_DIR', ROOT_DIR . 'lib/');

/** Absolute path to HTML templates. */
define('TEMPLATE_DIR', ROOT_DIR . 'templates/');

/** Absolute path to includes directory. */
define('INCLUDE_DIR', ROOT_DIR . 'includes/');

/** URL to web site. Default to something when running in CLI mode. */
define('HOST_URL',
       'http://' . ($serverName === NULL ? 'www.gamesnapper.com' : $_SERVER['SERVER_NAME']));

unset($serverName);
