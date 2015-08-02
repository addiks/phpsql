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

namespace Addiks\PHPSQL\BehaviourTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO;

/**
 * Tests if the USE statement works as intended.
 */
class UseTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:some_example_database");
    }

    public function testUseSQL()
    {
        
        ### PREPARE
        
        $expectedDatabaseId = "testDatabaseAfter";
        $beforeDatabaseId   = "testDatabaseBefore";
        
        $this->pdo->query("CREATE DATABASE ?", [$expectedDatabaseId]);
        $this->pdo->query("CREATE DATABASE ?", [$beforeDatabaseId]);
        $this->pdo->query("USE ?", [$beforeDatabaseId]);

        $checkedBeforeDatabaseId = reset($this->pdo->query("SELECT DATABASE()")->fetch());
        
        ### EXECUTE
        
        $this->pdo->query("USE ?", [$expectedDatabaseId]);
        
        ### COMPARE RESULTS
        
        $actualDatabaseId = reset($this->pdo->query("SELECT DATABASE();")->fetch());
        
        $this->assertEquals($checkedBeforeDatabaseId, $beforeDatabaseId, "Could not set database in preperation for 'USE' statement test!");
        $this->assertEquals($expectedDatabaseId, $actualDatabaseId, "Could not change database using the 'USE' statement!");
    }
}
