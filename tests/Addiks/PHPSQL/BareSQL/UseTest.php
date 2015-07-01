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

namespace Addiks\Database\Service\TestCase\BareSQL;

/**
 * Tests if the USE statement works as intended.
 * 
 * @author gerrit
 * @ phpunitTest
 */
class UseTest extends TestCase{
	
	public function testUseSQL(){
		
		### PREPARE
		
		/* @var $databaseResource Database */
		$databaseResource = $this->getDatabaseResource();
		
		$expectedDatabaseId = "testDatabaseAfter";
		$beforeDatabaseId = "testDatabaseBefore";
		
		$databaseResource->createSchema($expectedDatabaseId);
		$databaseResource->createSchema($beforeDatabaseId);
		$databaseResource->setCurrentlyUsedDatabaseId($beforeDatabaseId);
		
		### EXECUTE
		
		var_dump($databaseResource->listSchemas());
		
		$statementString = "USE {$expectedDatabaseId}";
		
		$databaseResource->query($statementString);
		
		### COMPARE RESULTS
		
		$actualDatabaseId = $databaseResource->getCurrentlyUsedDatabaseId();
		
		$this->assertEquals($expectedDatabaseId, $actualDatabaseId, "Could not change database using the 'USE' statement!");
		
	}
	
	public function testUseSQLWithParameters(){
	
		### PREPARE
	
		/* @var $databaseResource Database */
		$databaseResource = $this->getDatabaseResource();
	
		$expectedDatabaseId = "testDatabaseAfter";
		$beforeDatabaseId = "testDatabaseBefore";

		if(!$databaseResource->schemaExists($expectedDatabaseId)){
			$databaseResource->createSchema($expectedDatabaseId);
		}
		
		if(!$databaseResource->schemaExists($beforeDatabaseId)){
			$databaseResource->createSchema($beforeDatabaseId);
		}
		
		$databaseResource->setCurrentlyUsedDatabaseId($beforeDatabaseId);

		### EXECUTE

		$statementString = "USE :databaseId";

		$result = $databaseResource->query($statementString, [
			'databaseId' => $expectedDatabaseId,
		]);

		### COMPARE RESULTS

		$actualDatabaseId = $databaseResource->getCurrentlyUsedDatabaseId();

		$this->assertEquals($expectedDatabaseId, $actualDatabaseId, "Could not change database using the 'USE' statement!");
	
	}
	
}