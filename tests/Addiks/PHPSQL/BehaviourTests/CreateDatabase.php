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

/**
 * Tests if the USE statement works as intended.
 *
 * @author gerrit
 * @ phpunitTest
 */
class CreateDatabase extends TestCase
{
    
    public function testCreateDatabaseSQL()
    {
        
        ### PREPARE
        
        /* @var $databaseResource Database */
        $databaseResource = $this->getDatabaseResource();
        
        $databaseId = "createDatabaseTestDB";
    
        if ($databaseResource->schemaExists($databaseId)) {
            $databaseResource->removeSchema($databaseId);
        }
        
        ### EXECUTE
        
        $statementString = "CREATE DATABASE {$databaseId}";
        
        $databaseResource->query($statementString);
        
        ### COMPARE RESULTS
        
        $this->assertTrue($databaseResource->schemaExists($databaseId), "Failed executing 'CREATE DATABASE' statement!");
    }
    
    public function testCreateDatabaseSQLWithParameters()
    {
        
        ### PREPARE
        
        /* @var $databaseResource Database */
        $databaseResource = $this->getDatabaseResource();
        
        $databaseId = "createDatabaseTestDB";
    
        if ($databaseResource->schemaExists($databaseId)) {
            $databaseResource->removeSchema($databaseId);
        }
        
        ### EXECUTE
        
        $statementString = "CREATE DATABASE :databaseId";
        
        $databaseResource->query($statementString, [
            'databaseId' => $databaseId,
        ]);
        
        ### COMPARE RESULTS
        
        $this->assertTrue($databaseResource->schemaExists($databaseId), "Failed executing 'CREATE DATABASE' statement!");
    }
}
