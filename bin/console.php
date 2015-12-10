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

use Addiks\PHPSQL\PDO\PDO;
use Addiks\PHPSQL\Terminal;

require_once(dirname(__FILE__)."/bootstrap.php");

if (php_sapi_name() !== 'cli') {
    die("This script can only be used with CLI!");
}

if (count($argv) <= 1) {
    die("\n [USAGE] {$argv[0]} DSN\n\n");
}

$pdo = new PDO($argv[1]);

$terminal = Terminal::newFromPDO($pdo);
$terminal->run($argv);

