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

$tempDirectory = ROOT_DIR . '../tmp/';
$swfDirectory = ROOT_DIR . 'games/';
$imgDirectory = ROOT_DIR . 'img/games/';

$feedUrl = 'http://playtomic.com/games/feed/playtomic?format=xml&category=1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19&language=1,2,3,4,5,6,7,8,9,10,11,12&audience=0,1,2&minrating=40';

echo 'Fetching XML feed...', PHP_EOL, PHP_EOL;

$xml = simplexml_load_file($feedUrl);

if ($xml === FALSE) {
    echo 'Error loading XML feed', PHP_EOL;
    exit;
}

try {
    $db = new db(db::DEFAULT_DSN, db::WRITE_ACCESS);
} catch (Exception $e) {
    echo 'Could not connect to DB: ', $e->getMessage(), PHP_EOL;
    exit;
}

$dbResults = array();

$db->beginTransaction();

$sqlInsert = 'INSERT INTO game
              (title, description, instructions, filepath,
               active, width, height)
              VALUES
              (:title, :description, :instructions, :filepath,
               0, :width, :height)';

$sqlCheck = 'SELECT COUNT(id) AS num_games
             FROM   game
             WHERE  title = :title';

$insertStatement = $db->prepare($sqlInsert);
$checkStatement = $db->prepare($sqlCheck);

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

    $contents = unzipContents($workFile, $tempDirectory);

    if (!unlink($workFile)) {
        echo "\tCould not remove $workFile", PHP_EOL;
    }

    if (!validPull($contents)) {
        removePull($contents);
        continue;
    }

    $finalContents = parseContents($contents, $swfDirectory, $imgDirectory);

    if (!count($finalContents)) {
        removePull($contents);
        continue;
    }

    $dbResults[] = insertDb($db, $insertStatement, $g, $finalContents,
                            $swfDirectory);
}

if (!in_array(FALSE, $dbResults)) {
    $db->commit();
}

/**
 * @param string       $game
 * @param PDOStatement $statement
 *
 * @return boolean
 */
function gameExists($title, PDOStatement $statement) {
    $statement->bindParam(':title', $title, PDO::PARAM_STR, 128);
    $result = $statement->execute();

    if (!$result) {
        return TRUE;
    }

    if ($statement->fetchColumn() == 0) {
        return FALSE;
    }

    return TRUE;
}

/**
 * @param db               $db
 * @param PDOStatement     $statement
 * @param SimpleXMLElement $game
 * @param array            $contents
 * @param string           $swfDirectory
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

    return TRUE;
}

/**
 * @param array $contents
 */
function removePull(array $contents) {
    foreach ($contents as $file) {
        if (!unlink($file)) {
            echo "\tCould not remove $file", PHP_EOL;
        } else {
            echo "\tRemoved $file", PHP_EOL;
        }
    }
}

/**
 * @param array $contents
 *
 * @return boolean
 */
function validPull(array $contents) {
    if (!count($contents)) {
        echo "\tNothing downloaded", PHP_EOL;
        return FALSE;
    }

    $foundFlashFile = FALSE;

    foreach ($contents as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'swf') {
            $foundFlashFile = TRUE;
            break;
        }
    }

    if (!$foundFlashFile) {
        echo "\tCould not find Flash file", PHP_EOL;
        return FALSE;
    }

    return TRUE;
}

/**
 * @param array  $contents
 * @param string $swfDirectory
 * @param string $imgDirectory
 *
 * @return array
 */
function parseContents(array $contents, $swfDirectory, $imgDirectory) {
    $destination = array();
    $targetSubdir = str_pad(rand(1, 100), 5, '0', STR_PAD_LEFT);

    foreach ($contents as $file) {
        $swfTarget = $swfDirectory . $targetSubdir;
        $imgTarget = $imgDirectory . $targetSubdir;
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension == 'swf') {
            $targetDirectory = $swfTarget;
        } else {
            $targetDirectory = $imgTarget;
        }

        $destination[] = $targetDirectory . '/' . basename($file);

        if (!moveFile($file, $targetDirectory)) {
            return array();
        }
    }

    return $destination;
}

/**
 * @param string $source
 * @param string $targetDirectory
 *
 * @return boolean
 */
function moveFile($source, $targetDirectory) {
    if (!file_exists($targetDirectory) && !mkdir($targetDirectory, 0755)) {
        echo "\tCould not create $targetDirectory", PHP_EOL;
        return FALSE;
    }

    $target = $targetDirectory . '/' . basename($source);

    if (!rename($source, $target)) {
        echo "\tCould not move $source to $target", PHP_EOL;
        return FALSE;
    }

    echo "\tMoved ", basename($source), ' to ', $targetDirectory, PHP_EOL;
    return TRUE;
}

/**
 * @param string $filePath
 * @param string $tempDirectory
 *
 * @return array
 */
function unzipContents($filePath, $tempDirectory) {
    $contents = array();
    $zip = zip_open($filePath);

    if (!is_resource($zip)) {
        echo "Could not open zip", PHP_EOL;
        return $contents;
    }

    while ($entry = zip_read($zip)) {
        $filename = zip_entry_name($entry);
        $fileSize = zip_entry_filesize($entry);
        $f = fopen($tempDirectory . $filename, 'w');

        if ($f === FALSE) {
            echo "\tCould not create $filename", PHP_EOL;
            continue;
        }

        while ($fileSize > 0) {
            $readSize = min($fileSize, 10240);
            $fileSize -= $readSize;
            $content = zip_entry_read($entry, $readSize);
            if ($content !== FALSE) {
                fwrite($f, $content);
            }
        }

        fclose($f);

        $contents[] = $tempDirectory . $filename;
    }

    zip_close($zip);

    return $contents;
}

/**
 * @param string $url
 * @param string $tempDirectory
 *
 * @return string
 */
function downloadPackage($url, $tempDirectory) {
    $workFile = tempnam($tempDirectory, '');

    if ($workFile === FALSE || $workFile == '') {
        echo 'Could not get temp filename', PHP_EOL;
        return '';
    }

    $r = fopen($url, 'r');

    if ($r === FALSE) {
        echo "Could not download $url", PHP_EOL;
        return $workFile;
    }

    $w = fopen($workFile, 'w');

    if ($w === FALSE) {
        echo "Could not open write access to $workFile", PHP_EOL;
        fclose($r);
        return $workFile;
    }

    while (!feof($r)) {
        fwrite($w, fgets($r, 2048));
    }

    fclose($r);
    fclose($w);

    return $workFile;
}
