#!/usr/bin/php
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

$feedUrl = 'http://www.mochimedia.com/feeds/games/8897d1212df5b3f6/all/all'
         . '?limit=25';

echo 'Fetching XML feed...', PHP_EOL, PHP_EOL;

$xml = simplexml_load_file($feedUrl);

if ($xml === FALSE) {
    echo 'Error loading XML feed', PHP_EOL;
    exit;
}

$db = prepareDb();

$insertStatement = prepareInsertStatement($db);
$checkStatement = prepareCheckStatement($db);
$categoryStatement = prepareCategoryXrefInsertStatement($db);
$categoryId = getExistingCategories($db);

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

    $workFile = downloadPackage($zipUrl, $tempDirectory);

    if ($workFile == '') {
        echo "\tError downloading ZIP", PHP_EOL;
        continue;
    }

    echo "\tDownloaded ", number_format(filesize($workFile)), ' bytes',
         PHP_EOL;

    $contents = unzipContents($workFile, $tempDirectory,
                              basename($g->summary->div->a->img['src']),
                              substr(basename($zipUrl), 0, -3) . 'swf');

    $slug = findSlug($contents);

    // TODO: Use $g to find slug as well.
    if ($slug === NULL) {
        echo "\tCould not get slug", PHP_EOL;
        unlink($workFile);
        continue;
    }

    if (!unlink($workFile)) {
        echo "\tCould not remove $workFile", PHP_EOL;
    }

    $categories = getCategories($g->category);

    if (!validPull($contents)) {
        removePull($contents);
        continue;
    }

    filterContents($contents, $slug);

    $finalContents = moveContentsToFinalDestination($contents, $swfDirectory,
                                                    $imgDirectory);

    if (!count($finalContents)) {
        removePull($contents);
        continue;
    }

    $x = explode('x', getSummary($g->summary, 'Resolution'));
    $width = $x[0];
    $height = $x[1];

    $db->beginTransaction();

    $gameId = insertDb($db, $insertStatement, $finalContents, $swfDirectory,
                       $g->title,
                       getSummary($g->summary, 'Description'),
                       getSummary($g->summary, 'Instructions'),
                       $width, $height);

    if ($gameId == FALSE) {
        $db->rollBack();
        removePull($finalContents);
        continue;
    }

    if (!associateCategories($categories, $db, $categoryId,
                             $categoryStatement, $gameId)) {
        $db->rollBack();
        removePull($finalContents);
        continue;
    }

    $db->commit();
}

/**
 * @param array $contents
 *
 * @return string
 */
function findSlug(array $contents) {
    foreach ($contents as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'swf') {
            return pathinfo($file, PATHINFO_FILENAME);
        }
    }

    return NULL;
}

/**
 * @param array  &$contents
 * @param string $slug
 */
function filterContents(array &$contents, $slug) {
    $numItems = count($contents);

    for ($i = 0; $i < $numItems; $i++) {
        $extension = pathinfo($contents[$i], PATHINFO_EXTENSION);
        $filename = pathinfo($contents[$i], PATHINFO_FILENAME);

        if ($filename == '_thumb_100x100') {
            $target = dirname($contents[$i]) . "/$slug.$extension";

            if (!rename($contents[$i], $target)) {
                echo "\tCould not move ", $contents[$i], " to $target", PHP_EOL;
            } else {
                $contents[$i] = $target;
            }

            continue;
        } else if ($extension == 'swf') {
            continue;
        }

        if (!unlink($contents[$i])) {
            echo "\tCould not remove ", $contents[$i], PHP_EOL;
        }

        unset($contents[$i]);
    }
}

/**
 * @param SimpleXMLElement $category
 *
 * @return array
 */
function getCategories(SimpleXMLElement $category) {
    $categories = array();

    foreach ($category as $c) {
        $categories[] = (string) $c['term'];
    }

    return $categories;
}

/**
 * @param SimpleXMLElement $summary
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
 * @param SimpleXMLElement $summary
 * @param string           $needle
 *
 * @return string
 */
function getSummary(SimpleXMLElement $summary, $needle) {
    if (!isset($summary->div->dl->dt)) {
        return NULL;
    }

    $needle = strtolower($needle);
    $i = 0;

    foreach ($summary->div->dl->dt as $k => $title) {
        if (strtolower($title) == $needle) {
            if (isset($summary->div->dl->dd[$i]) &&
                    !empty($summary->div->dl->dd[$i][0])) {
                return $summary->div->dl->dd[$i][0];
            }
        }
        $i++;
    }

    return NULL;
}
