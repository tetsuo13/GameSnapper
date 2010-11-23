<?php
/**
 *
 *
 * @copyright 2010 GameSnapper
 * @since     2010-11-20
 * @author    Andrei Nicholson
 */

/**
 */
class db extends PDO {
    const DEFAULT_DSN = 'pgsql:host=localhost;dbname=neoanime_gamesnapper';
    const READ_ONLY_ACCESS = 0x01;
    const WRITE_ACCESS = 0x02;

    /**
     */
    public function __construct($dsn, $method) {
        switch ($method) {
            case self::READ_ONLY_ACCESS:
                $username = 'neoanime_gsro';
                $password = '53AdeGOi1oDr';
                break;

            case self::WRITE_ACCESS:
                $username = 'neoanime_gsrw';
                $password = 'gqUBwHToO5RB';
                break;

            default:
                throw new Exception('Invalid method');
        }

        parent::__construct($dsn, $username, $password);

        $this->exec("SET TIME ZONE 'America/New_York'");
    }
}
