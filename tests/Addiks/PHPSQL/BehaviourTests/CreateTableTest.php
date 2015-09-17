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

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO\PDO;

/**
 * Tests if CREATE TABLE works as expected
 */
class CreateTableTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testSimpleCreateTable()
    {
        
        ### PREPARE

        $expectedTableName = "phpunit_test";

        ### EXECUTE

        $this->pdo->query("
            CREATE TABLE ? (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo VARCHAR(32),
                bar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                baz DECIMAL(4,2)
            )
        ", [$expectedTableName]);

        ### COMPARE RESULTS

        $result = $this->pdo->query("SHOW TABLES");
        $actualTables = array();
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualTables[] = $row['TABLE'];
        }

        $this->assertContains($expectedTableName, $actualTables, "Could not create table!");
    }

}
