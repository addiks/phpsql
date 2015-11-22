<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.    
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */

if (!defined("ADDIKS_PHPSQL_BASEDIR")) {
    define("ADDIKS_PHPSQL_BASEDIR", realpath(__DIR__."/.."));

    function addiks_phpsql_auto_loader($className)
    {
        $filePath = str_replace("\\", "/", $className);
        $filePath = ADDIKS_PHPSQL_BASEDIR."/src/{$filePath}.php";
        if (file_exists($filePath)) {
            require_once($filePath);
        }
    }

    spl_autoload_register('addiks_phpsql_auto_loader');
}
