<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviourTests;

use Addiks\PHPSQL\PDO;
use PHPUnit_Framework_TestCase;

/**
 * Tests if SHOW TABLES works as expected
 */
class ShowTablesTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testShowTables()
    {
        ### PREPARE

        $expectedTables = [
            'phpunit_foo',
            'phpunit_bar',
            'phpunit_baz',
        ];

        foreach ($expectedTables as $expectedTableName) {
            $this->pdo->query("
                CREATE TABLE ? (id INT PRIMARY KEY NOT NULL AUTO_INCREMENT)
            ", [$expectedTableName]);
        }

        ### EXECUTE

        $result = $this->pdo->query("SHOW TABLES");

        ### COMPARE RESULTS

        $actualTables = array();
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualTables[] = $row['TABLE'];
        }

        $this->assertEmpty(array_diff($expectedTables, $actualTables));
    }
}
