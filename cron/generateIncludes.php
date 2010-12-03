#!/usr/bin/php
<?php
/**
 * Creates a cache of often-used data out of the database.
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-30
 * @author    Andrei Nicholson
 */

require_once '../lib/globals.php';
require_once LIB_DIR . 'lib.db.php';

try {
    $db = new db(db::DEFAULT_DSN, db::READ_ONLY_ACCESS);
} catch (Exception $e) {
    echo 'Could not connect to DB: ', $e->getMessage(), PHP_EOL;
    exit;
}

categoryInclude($db);

/**
 * @param db $db
 */
function categoryInclude(db $db) {
    $category = array();
    $file = fopen(INCLUDE_DIR . 'inc.category.php', 'w');

    if ($file === FALSE) {
        return;
    }

    fwrite($file, '<?php
/**
 * Category cache.
 *
 * @copyright ' . date('Y') . ' GameSnapper
 * @since     ' . date('c') . '
 */

');

    $sql = 'SELECT   id, title, homepage
            FROM     category
            ORDER BY title';

    foreach ($db->query($sql) as $row) {
        $category[$row['id']] = array('id'       => $row['id'],
                                      'title'    => $row['title'],
                                      'homepage' => $row['homepage']);
    }

    fwrite($file, '$category = ' . var_export($category, TRUE) . ';');
    fclose($file);
}
