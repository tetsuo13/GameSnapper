<?php
/**
 * Constants and other things all scripts need.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-18
 * @author    Andrei Nicholson
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

/** Absolute path to base directory. */
define('ROOT_DIR', realpath(dirname(__FILE__) . '/..') . '/');

/** Absolute path to PHP library files. */
define('LIB_DIR', ROOT_DIR . 'lib/');

/** Absolute path to HTML templates. */
define('TEMPLATE_DIR', ROOT_DIR . 'templates/');

define('HOST_URL', 'http://' . $_SERVER['SERVER_NAME']);
