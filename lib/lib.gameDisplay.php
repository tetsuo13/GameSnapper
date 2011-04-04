<?php
/**
 * Functions related to game data and display.
 *
 * @copyright 2010-2011 GameSnapper
 * @since     2010-12-05
 * @author    Andrei Nicholson
 */

/**
 * Trims words off of string of text.
 *
 * @param $text  String to trim.
 * @param $limit Number of characters to display.
 *
 * @return string Trimmed string.
 */
function trimText($text, $limit) {
    $extra = '...';
    preg_match_all('/(\S+\s+)/', strip_tags($text), $matches);
    if ($limit < count($matches[0])) {
        return rtrim(implode('', array_slice($matches[0], 0, $limit))) . $extra;
    }
    return $text;
}

/**
 * Generate link to the browse games by category page.
 *
 * @param string $title Category title.
 *
 * @return string Full URL.
 */
function categoryLink($title) {
    return HOST_URL . '/' . urlencode(strtolower($title) . '-games');
}
