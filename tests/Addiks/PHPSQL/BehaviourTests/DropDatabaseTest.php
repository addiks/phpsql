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
use Addiks\PHPSQL\PDO;

class DropDatabaseTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testDropDatabase()
    {

        ### PREPARE

        $expectedDatabaseName = "databaseToDelete";

        $this->pdo->query("CREATE DATABASE ?", [$expectedDatabaseName]);

        ### EXECUTE

        $this->pdo->query("DROP DATABASE ?", [$expectedDatabaseName]);

        ### CHECK RESULTS

        $result = $this->pdo->query("SHOW DATABASES");

        $actualDatabases = array();
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualDatabases[] = $row['DATABASE'];
        }

        $this->assertNotContains($expectedDatabaseName, $actualDatabases, "Could not drop database!");
    }
}
