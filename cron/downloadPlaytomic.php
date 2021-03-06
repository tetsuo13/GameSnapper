#!/usr/bin/php
<?php
/**
 * Download from Playtomic.
 *
 * @copyright 2010-2011 GameSnapper
 * @since     2010-11-18
 * @author    Andrei Nicholson
 */

require_once '../lib/globals.php';
require_once LIB_DIR . 'lib.db.php';
require_once './lib.download.php';

$feedUrl = 'http://playtomic.com/games/feed/playtomic'
         . '?format=xml'
         . '&category=1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19'
         . '&language=1,2,3,4,5,6,7,8,9,10,11,12'
         . '&audience=0,1,2'
         . '&minrating=50'
         . '&limit=25';

echo 'Fetching XML feed...', PHP_EOL, PHP_EOL;

$xml = simplexml_load_file($feedUrl);

if ($xml === FALSE) {
    echo 'Error loading XML feed', PHP_EOL;
    exit;
}

$db = prepareDb();

$insertStatement = prepareInsertStatement($db);
$vendorFeedStatement = prepareVendorFeedStatement($db);
$vendorCheckStatement = prepareVendorFeedCheckStatement($db);
$categoryStatement = prepareCategoryXrefInsertStatement($db);
$categoryId = getExistingCategories($db);

foreach ($xml->game as $g) {
    echo PHP_EOL, 'Processing ', $g->title, PHP_EOL;

    if (gameExists($g->title, $vendorCheckStatement)) {
        echo "\tTitle already exists", PHP_EOL;
        continue;
    }

    if ($g->swf_size > $maxSwfSize) {
        echo "\tTitle reported larger than ", number_format($maxSwfSize),
             ' MB: ', number_format($g->swf_size), PHP_EOL;
        continue;
    }

    $workFile = downloadPackage($g->zip_url, $tempDirectory);

    if ($workFile == '') {
        echo "\tError", PHP_EOL;
        continue;
    }

    echo "\tDownloaded ", number_format(filesize($workFile)), ' bytes',
         PHP_EOL;

    $contents = unzipContents($workFile, $tempDirectory,
                              basename((string) $g->thumbnail_url));

    if (!unlink($workFile)) {
        echo "\tCould not remove $workFile", PHP_EOL;
    }

    if (!validPull($contents)) {
        removePull($contents);
        continue;
    }

    $finalContents = moveContentsToFinalDestination($contents, $swfDirectory,
                                                    $imgDirectory);

    if (!count($finalContents)) {
        removePull($contents);
        continue;
    }

    $db->beginTransaction();

    $gameId = insertGame($db, $insertStatement, $finalContents, $swfDirectory,
                         $g->title, $g->description, $g->instructions,
                         $g->width, $g->height, $vendorIdPlaytomic);

    if ($gameId == FALSE) {
        $db->rollBack();
        removePull($finalContents);
        continue;
    }

    // Comes in as a string: "Foo", "Bar", "Baz"
    $categories = explode('", "', substr((string) $g->categories, 1, -1));

    if (!associateCategories($categories, $db, $categoryId,
                             $categoryStatement, $gameId)) {
        $db->rollBack();
        removePull($finalContents);
        continue;
    }

    if (!insertVendorFeed($db, $vendorFeedStatement, $vendorIdPlaytomic,
                          $g->title, $gameId)) {
        $db->rollBack();
        removePull($finalContents);
        continue;
    }


    $db->commit();
}
