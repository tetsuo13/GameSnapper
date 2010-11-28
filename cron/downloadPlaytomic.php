#!/usr/bin/php
<?php
/**
 * Download from Playtomic.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-18
 * @author    Andrei Nicholson
 */

require_once '../lib/globals.php';
require_once LIB_DIR . 'lib.db.php';
require_once './lib.download.php';

$feedUrl = 'http://playtomic.com/games/feed/playtomic?format=xml&category=1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19&language=1,2,3,4,5,6,7,8,9,10,11,12&audience=0,1,2&minrating=50';

echo 'Fetching XML feed...', PHP_EOL, PHP_EOL;

$xml = simplexml_load_file($feedUrl);

if ($xml === FALSE) {
    echo 'Error loading XML feed', PHP_EOL;
    exit;
}

$db = prepareDb();

$gameId = array();

$db->beginTransaction();

$insertStatement = prepareInsertStatement($db);
$checkStatement = prepareCheckStatement($db);
$categoryStatement = prepareCategoryXrefInsertStatement($db);
$categoryId = getExistingCategories($db);

$count = 0;

foreach ($xml->game as $g) {
    echo PHP_EOL, 'Processing ', $g->title, PHP_EOL;

    if (gameExists($g->title, $checkStatement)) {
        echo "\tTitle already exists", PHP_EOL;
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

    $gameId[] = insertDb($db, $insertStatement, $g, $finalContents,
                            $swfDirectory);

    if (end($gameId) == FALSE) {
        continue;
    }

    // Comes in as a string: "Foo", "Bar", "Baz"
    $categories = explode('", "', substr((string) $g->categories, 1, -1));

    if (!associateCategories($categories, $db, $categoryId,
                             $categoryStatement, end($gameId))) {
        continue;
    }

    $count++;
    if ($count >= 1) {
        break;
    }
}

if (!in_array(FALSE, $gameId)) {
    $db->commit();
}

/**
 * @param array        $categories Category names game is associated with.
 * @param db           $db         Database handle.
 * @param array        $categoryId Existing category ID table.
 * @param PDOStatement $statement  category_game_xref insert statement.
 * @param int          $gameId     ID of last game inserted.
 *
 * @return boolean
 */
function associateCategories(array $categories, db $db, array $categoryId,
                             PDOStatement $statement, $gameId) {
    foreach ($categories as $title) {
        if (!isset($categoryId[$title])) {
            $categoryId[$title] = insertCategory($db, $title);
        }

        $statement->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $statement->bindParam(':category_id', $categoryId[$title], PDO::PARAM_INT);
        $result = $statement->execute();

        if (!$result) {
            echo "\tCould not associated game ID $gameId with category $title (",
                 $categoryId[$title], ')', PHP_EOL;
            print_r($statement->errorInfo());
            $db->rollBack();
            return FALSE;
        }
    }
    return TRUE;
}

/**
 * @param db               $db
 * @param PDOStatement     $statement
 * @param SimpleXMLElement $game
 * @param array            $contents
 * @param string           $swfDirectory
 *
 * @return int Game ID or FALSE if any error.
 */
function insertDb(db $db, PDOStatement $statement, SimpleXMLElement $game,
                  array $contents, $swfDirectory) {
    $filePath = '';

    foreach ($contents as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'swf') {
            $filePath = substr($file, strlen($swfDirectory));
            $filePath = substr($filePath, 0, -4);
            break;
        }
    }

    if ($filePath == '') {
        echo "\tCould not determine file path", PHP_EOL;
        return FALSE;
    }

    $statement->bindParam(':title', $game->title, PDO::PARAM_STR, 128);
    $statement->bindParam(':description', $game->description, PDO::PARAM_STR, 1024);
    $statement->bindParam(':instructions', $game->instructions, PDO::PARAM_STR, 1024);
    $statement->bindParam(':filepath', $filePath, PDO::PARAM_STR, 128);
    $statement->bindParam(':width', $game->width, PDO::PARAM_INT);
    $statement->bindParam(':height', $game->height, PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
        echo "\tCould not insert game record", PHP_EOL;
        print_r($statement->errorInfo());
        $db->rollBack();
        return FALSE;
    }

    return $db->lastInsertId('game_id_seq');
}
