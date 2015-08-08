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

namespace Addiks\PHPSQL\Service\TestCase\BareSQL;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO;

/**
 * Tests if CREATE DATABASE statement works as intended.
 */
class CreateDatabaseTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    public function testCreateDatabase()
    {
        
        ### PREPARE
        
        $databaseId = "createDatabaseTestDB";

        ### EXECUTE
        
        $this->pdo->query("CREATE DATABASE ?", [$databaseId]);
        
        ### COMPARE RESULTS

        $result = $this->pdo->query("SHOW DATABASES");

        $actualDatabases = array();
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualDatabases[] = $row['DATABASE'];
        }

        $this->assertContains($databaseId, $actualDatabases, "Could not create database!");
    }
    
}
