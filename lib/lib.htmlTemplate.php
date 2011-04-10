<?php
/**
 *
 *
 * @copyright 2010-2011 GameSnapper
 * @since     2010-11-18
 * @author    Andrei Nicholson
 */

require_once LIB_DIR . '/PHPTAL.php';

/**
 */
class htmlTemplate extends PHPTAL {
    /**
     * @param string $templatePath
     */
    public function __construct($templatePath) {
        parent::__construct($templatePath);

        $this->setPhpCodeDestination(ROOT_DIR . 'templateCache');
        $this->setOutputMode(PHPTAL::HTML5);
        $this->stripComments(TRUE);
    }
}
