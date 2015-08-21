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

class ShowDatabasesTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testShowDatabases()
    {
        
        ### PREPARE

        $expextedDatabases = [
            'phpunit_showdatabases_foo',
            'phpunit_showdatabases_bar',
            'phpunit_showdatabases_baz',
        ];

        foreach ($expextedDatabases as $databaseName) {
            $this->pdo->query("CREATE DATABASE ?", [$databaseName]);
        }

        ### EXECUTE

        $result = $this->pdo->query("SHOW DATABASES");

        ### COMPARE RESULTS

        $actualDatabaseNames = array();
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualDatabaseNames[] = $row['DATABASE'];
        }
        
        $this->assertEmpty(array_diff($expextedDatabases, $actualDatabaseNames), "Could not correctly show databases!");
    }
}
