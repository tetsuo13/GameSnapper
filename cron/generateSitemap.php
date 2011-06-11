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
require_once LIB_DIR . 'lib.gameDisplay.php';

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
fwrite($xmlFile,
       '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

fwrite($xmlFile, addUrl(HOST_URL, 'weekly'));

$sql = 'SELECT   id, filepath, slug, added
        FROM     game
        ORDER BY added DESC';

foreach ($db->query($sql) as $row) {
    $location = HOST_URL . '/play/' . $row['filepath'] . '-' . $row['slug'];
    $lastModified = date('c', strtotime($row['added']));
    fwrite($xmlFile, addUrl($location, 'monthly', $lastModified, 0.8));
}

fwrite($xmlFile, addUrl(HOST_URL . '/gaming', 'weekly'));

$sql = 'SELECT id, title, homepage
        FROM   category';

foreach ($db->query($sql) as $row) {
    fwrite($xmlFile, addUrl(categoryLink($row['title']), 'weekly'));
}

fwrite($xmlFile, '</urlset>');

/**
 * @param string $location
 * @param string $changeFrequency
 * @param string $lastModified
 * @param float  $priority
 *
 * @return string
 */
function addUrl($location, $changeFrequency, $lastModified = NULL,
                $priority = NULL) {
    return '<url>' .
           "<loc>$location</loc>" .
           ($lastModified !== NULL ? "<lastmod>$lastModified</lastmod>" : '') .
           "<changefreq>$changeFrequency</changefreq>" .
           ($priority !== NULL ? "<priority>$priority</priority>" : '') .
           '</url>';
}
