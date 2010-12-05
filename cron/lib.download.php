<?php
/**
 * Common functions for all download scripts.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-23
 * @author    Andrei Nicholson
 */

$tempDirectory = ROOT_DIR . 'tmp/';
$swfDirectory = ROOT_DIR . 'htdocs/games/';
$imgDirectory = ROOT_DIR . 'htdocs/img/games/';

/**
 * @param string       $title
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
 * @return db
 */
function prepareDb() {
    try {
        return new db(db::DEFAULT_DSN, db::WRITE_ACCESS);
    } catch (Exception $e) {
        echo 'Could not connect to DB: ', $e->getMessage(), PHP_EOL;
        exit;
    }
}

/**
 * @param db $db
 *
 * @return PDOStatement
 */
function prepareCategoryInsertStatement(db $db) {
    $sqlInsert = 'INSERT INTO category
                  (title, homepage)
                  VALUES
                  (:title, 0)';
    return $db->prepare($sqlInsert);
}

/**
 * @param db $db
 *
 * @return PDOStatement
 */
function prepareCategoryXrefInsertStatement(db $db) {
    $sqlInsert = 'INSERT INTO category_game_xref
                  (game_id, category_id)
                  VALUES
                  (:game_id, :category_id)';
    return $db->prepare($sqlInsert);
}

/**
 * @param db $db
 *
 * @return PDOStatement
 */
function prepareInsertStatement(db $db) {
    $sqlInsert = 'INSERT INTO game
                  (title, description, instructions, filepath,
                   active, width, height, slug,
                   thumbtype)
                  VALUES
                  (:title, :description, :instructions, :filepath,
                   0, :width, :height, :slug,
                   :thumbtype)';
    return $db->prepare($sqlInsert);
}

/**
 * @param db $db
 *
 * @return PDOStatement
 */
function prepareCheckStatement(db $db) {
    $sqlCheck = 'SELECT COUNT(id) AS num_games
                 FROM   game
                 WHERE  title = :title';
    return $db->prepare($sqlCheck);
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

/**
 * @param string $filePath
 * @param string $tempDirectory
 * @param string $thumbnail
 * @param string $flashFile
 *
 * @return array
 */
function unzipContents($filePath, $tempDirectory, $thumbnail,
                       $flashFile = NULL) {
    $contents = array();
    $zip = zip_open($filePath);

    if (!is_resource($zip)) {
        echo "Could not open zip", PHP_EOL;
        return $contents;
    }

    if ($flashFile === NULL) {
        $flashFile = substr($thumbnail, 0, -3) . 'swf';
    }

    while ($entry = zip_read($zip)) {
        $filename = basename(zip_entry_name($entry));

        if ($filename != $thumbnail && $filename != $flashFile) {
            continue;
        }

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
 * @param array  $contents
 * @param string $swfDirectory
 * @param string $imgDirectory
 *
 * @return array
 */
function moveContentsToFinalDestination(array $contents, $swfDirectory,
                                        $imgDirectory) {
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
 * @param db $db
 *
 * @return array
 */
function getExistingCategories(db $db) {
    $categoryId = array();

    $sql = 'SELECT   id, title
            FROM     category
            ORDER BY title';

    foreach ($db->query($sql) as $row) {
        $categoryId[$row['title']] = $row['id'];
    }

    return $categoryId;
}

/**
 * @param db     $db
 * @param string $title
 *
 * @return int
 */
function insertCategory(db $db, $title) {
    static $statement = NULL;

    if ($statement === NULL) {
        $statement = prepareCategoryInsertStatement($db);
    }

    $statement->bindParam(':title', $title, PDO::PARAM_STR, 64);

    $result = $statement->execute();

    if (!$result) {
        return FALSE;
    }

    return $db->lastInsertId('category_id_seq');
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
            return FALSE;
        }
    }
    return TRUE;
}

/**
 * @param db           $db
 * @param PDOStatement $statement
 * @param array        $contents
 * @param string       $swfDirectory
 * @param string       $title
 * @param string       $description
 * @param string       $instructions
 * @param int          $width
 * @param int          $height
 *
 * @return int Game ID or FALSE if any error.
 */
function insertGame(db $db, PDOStatement $statement, array $contents,
                    $swfDirectory, $title, $description, $instructions,
                    $width, $height) {
    $filePath = '';
    $slug = '';
    $thumbType = '';

    foreach ($contents as $file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($extension == 'swf') {
            // Keep only filepath + slug.
            $filePath = substr($file, strlen($swfDirectory));

            // Everything after filepath without extension.
            $slug = substr($filePath, 6, -4);

            // Everything up to first slash.
            $filePath = substr($filePath, 0, 5);
        } else {
            $thumbType = $extension;
        }
    }

    if ($filePath == '' || $slug == '') {
        echo "\tCould not determine file path", PHP_EOL;
        return FALSE;
    }

    $statement->bindParam(':title', $title, PDO::PARAM_STR, 128);
    $statement->bindParam(':description', $description, PDO::PARAM_STR, 1024);
    $statement->bindParam(':instructions', $instructions, PDO::PARAM_STR, 1024);
    $statement->bindParam(':filepath', $filePath, PDO::PARAM_STR, 16);
    $statement->bindParam(':slug', $slug, PDO::PARAM_STR, 64);
    $statement->bindParam(':width', $width, PDO::PARAM_INT);
    $statement->bindParam(':height', $height, PDO::PARAM_INT);
    $statement->bindParam(':thumbtype', $thumbType, PDO::PARAM_STR, 8);

    $result = $statement->execute();

    if (!$result) {
        echo "\tCould not insert game record", PHP_EOL;
        print_r($statement->errorInfo());
        return FALSE;
    }

    return $db->lastInsertId('game_id_seq');
}
