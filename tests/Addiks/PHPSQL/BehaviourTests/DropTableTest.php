<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviorTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO;

class DropTableTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testDropTable()
    {
        ### PREPARE

        $exptectedTableName = "phpunit_droptable_testtable";

        $this->pdo->query("CREATE TABLE ? (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT)", [$exptectedTableName]);

        ### EXECUTE

        $this->pdo->query("DROP TABLE ?", [$exptectedTableName]);

        ### CHECK RESULTS

        $result = $this->pdo->query("SHOW TABLES");
        $actualTables = array_values($result->fetchAll(PDO::FETCH_ASSOC));

        $this->assertNotContains($exptectedTableName, $actualTables, "Could not drop table!");
    }
}
