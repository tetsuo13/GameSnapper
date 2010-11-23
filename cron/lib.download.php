<?php
/**
 * Common functions for all download scripts.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-23
 * @author    Andrei Nicholso
 */

$tempDirectory = ROOT_DIR . '../tmp/';
$swfDirectory = ROOT_DIR . 'games/';
$imgDirectory = ROOT_DIR . 'img/games/';

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
 * @return PDOStatement
 */
function prepareInsertStatement(db $db) {
    $sqlInsert = 'INSERT INTO game
                  (title, description, instructions, filepath,
                   active, width, height)
                  VALUES
                  (:title, :description, :instructions, :filepath,
                   0, :width, :height)';

    return $db->prepare($sqlInsert);
}

/**
 * @return PDOStatement
 */
function prepareCheckStatement(db $db) {
    $sqlCheck = 'SELECT COUNT(id) AS num_games
                 FROM   game
                 WHERE  title = :title';

    return $db->prepare($sqlCheck);
}
