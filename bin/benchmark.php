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

require_once(dirname(__FILE__)."/bootstrap.php");

if (isset($argv[1]) && $argv[1] === 'mysql') {
    $dsn = "mysql:host=127.0.0.1;dbname=benchmark";
    $pdo = new \PDO($dsn, "benchmark", "benchmark");

} else {
    $dsn = "inmemory:benchmark";
    $pdo = new PDO($dsn);
}

echo " - opened database '{$dsn}'.\n";

echo " - creating table.\n";

$pdo->query("
    CREATE TABLE benchmark_table (
        id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
        foo VARCHAR(128),
        bar DECIMAL(5,3),
        baz DATETIME
    );
");

$insertStatement = $pdo->prepare("
    INSERT INTO benchmark_table
        (foo, bar, baz)
    VALUES
        (?, ?, ?)
");

$selectStatement = $pdo->prepare("SELECT * FROM benchmark_table WHERE id = ?");

$starttime = microtime(true);

srand(0);

$insertCount = 10000;

echo " - inserting {$insertCount} rows.\n";

for ($i = 1; $i <= $insertCount; $i++) {
    $insertStatement->execute([
        md5($i) . md5($i . '-2'),
        rand(0, 10000000) * 0.001,
        date("Y-m-d H:i:s", rand(0, 2007583645))
    ]);

    if ($i % 1000 === 0) {
        echo " - ({$i} / {$insertCount})\n";
    }
}

$stoptime = microtime(true);

$seconds = round($stoptime - $starttime, 3);

echo " - inserting {$insertCount} rows took {$seconds} seconds.\n";

$selectCount = 10000;

echo " - selecting {$selectCount} rows individually by id\n";

$starttime = microtime(true);

for ($i = 1; $i <= $selectCount; $i++) {
    $selectStatement->execute([rand(1, $insertCount)]);

    if ($i % 1000 === 0) {
        echo " - ({$i} / {$selectCount})\n";
    }
}

$stoptime = microtime(true);

$seconds = round($stoptime - $starttime, 3);

echo " - selecting {$selectCount} rows took {$seconds} seconds.\n";

