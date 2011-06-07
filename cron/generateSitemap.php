#!/usr/bin/php
<?php
/**
 * Creates the sitemap.xml file in htdocs.
 *
 * @copyright 2010-2011 GameSnapper
 * @since     2010-11-28
 * @author    Andrei Nicholson
 */

require_once '../lib/globals.php';
require_once LIB_DIR . 'lib.db.php';

define('SITEMAP_FILENAME', ROOT_DIR . 'htdocs/sitemap.xml');

$xmlFile = fopen(SITEMAP_FILENAME, 'w');

if ($xmlFile === FALSE) {
    echo 'Could not open ', SITEMAP_FILENAME, PHP_EOL;
    exit;
}

try {
    $db = new db(db::DEFAULT_DSN, db::READ_ONLY_ACCESS);
} catch (Exception $e) {
    echo 'Could not connect to DB: ', $e->getMessage(), PHP_EOL;
    exit;
}

fwrite($xmlFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
fwrite($xmlFile, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

$sql = 'SELECT   id, filepath, slug, added
        FROM     game
        ORDER BY added DESC';

foreach ($db->query($sql) as $row) {
    fwrite($xmlFile, '<url>');
    fwrite($xmlFile,
           '<loc>' . HOST_URL . '/play/' . $row['filepath'] . '-' . $row['slug'] . '</loc>');
    fwrite($xmlFile,
           '<lastmod>' . date('c', strtotime($row['added'])) . '</lastmod>');
    fwrite($xmlFile, '<changefreq>monthly</changefreq>');
    fwrite($xmlFile, '</url>');
}

fwrite($xmlFile, '</urlset>');
