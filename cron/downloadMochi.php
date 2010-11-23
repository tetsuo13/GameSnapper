<?php
/**
 * Download from Mochi.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-23
 * @author    Andrei Nicholson
 */

require_once '../lib/globals.php';
require_once LIB_DIR . 'lib.db.php';
require_once './lib.download.php';

$feedUrl = 'http://www.mochimedia.com/feeds/games/8897d1212df5b3f6/all/all?limit=25';

echo 'Fetching XML feed...', PHP_EOL, PHP_EOL;

$xml = simplexml_load_file($feedUrl);

if ($xml === FALSE) {
    echo 'Error loading XML feed', PHP_EOL;
    exit;
}

$db = prepareDb();

$dbResults = array();

$db->beginTransaction();

$insertStatement = prepareInsertStatement($db);
$checkStatement = prepareCheckStatement($db);

foreach ($xml->entry as $g) {
    echo PHP_EOL, 'Processing ', $g->title, PHP_EOL;

    if (gameExists($g->title, $checkStatement)) {
        echo "\tTitle already exists", PHP_EOL;
        continue;
    }

    $zipUrl = getZipUrl($g->summary);

    if ($zipUrl === NULL) {
        echo "\tCould not find URL to archive", PHP_EOL;
        continue;
    }

    echo $zipUrl, PHP_EOL;
/*
    $flashFile = downloadFlashObject($g->link);

    if ($flashFile === NULL) {
        echo "\tCould not locate URL to Flash object", PHP_EOL;
        continue;
    }
*/
//print_r($g);
//exit;
}

/**
 * @param SimpleXMLElement $link
 *
 * @return string
 */
function getZipUrl(SimpleXMLElement $summary) {
    if (!isset($summary->div->dl->dt)) {
        return NULL;
    }

    $i = 0;

    foreach ($summary->div->dl->dt as $k => $title) {
        if (strtolower($title) == 'zip file') {
            if (isset($summary->div->dl->dd[$i]) &&
                    $summary->div->dl->dd[$i]['class'] == 'zip_url' &&
                    !empty($summary->div->dl->dd[$i][0])) {
                return $summary->div->dl->dd[$i][0];
            }
        }
        $i++;
    }

    return NULL;
}




/**
 * @param SimpleXMLElement $link
 *
 * @return string
 */
function downloadFlashObject(SimpleXMLElement $link) {
    $url = getUrlToFlashObject($link);

    if ($url === NULL) {
        return $url;
    }

    $filename = basename($url);
}

/**
 * @param SimpleXMLElement $link
 *
 * @return string
 */
function getUrlToFlashObject(SimpleXMLElement $link) {
    $objectUrl = '';

    foreach ($g->link as $l) {
        if (!isset($l['type']) || !isset($l['href']) ||
                $l['type'] != 'application/x-shockwave-flash') {
            continue;
        }

        $objectUrl = $l['href'];
        break;
    }

    if ($objectUrl == '') {
        return NULL;
    }
}
