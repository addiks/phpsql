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

namespace Addiks\Database\Service;

use Addiks\Workflow\Entity\SetupState;

use Addiks\Workflow\Service\Setup;
use Addiks\Workflow\Value\Enum\State;
use Addiks\Database\Value\Database\Dsn\Internal;
use Addiks\Workflow\Application;
use Addiks\Common\Service\MetaDataCache;
use Addiks\Database\Resource\Connection;
use Addiks\Database\Entity\Configuration\Database;
use Addiks\Database\Resource\EntityManager;

/**
 * This is a process that will be executed during the system-setup.
 * It will index all ORM-related entities and prepare the database.
 */
class SetupStep{
	
	/**
	 * This searches the system for components that need to be stored in the database.
	 * It then prepares the database for being able to store these components.
	 * 
	 * @Addiks\Setup(id="database_entities", title="Indexing database entities", order=3000, timeout=60)
	 * @param Setup $setup
	 */
	public function process(Setup $setup, SetupState $state){
		
		/* @var $application Application */
		$application = $setup->getFramework()->getApplication();
		
		/* @var $metaDataCache MetaDataCache */
		$application->factorize($metaDataCache);
		
		/* @var $dbConn Connection */
		$application->factorize($dbConn);
		
		/* @var $dbConfig Database */
		$dbConfig = $dbConn->getDatabaseConnectionConfig();
		
		// internal database-system, using database(-name) 'default'
		$dbConfig->setDsn(Internal::factory("internal:default"));
		
		$dbConn->storeConfiguration($dbConfig);
		
		/* @var $entityManager EntityManager */
		$application->factorize($entityManager);
		
		$setup->tick();
		
		foreach($metaDataCache->getModuleClasses() as $classId){
		
			$annotations = $metaDataCache->getAnnotationsByClass($classId);
		
			foreach($annotations as $key => $values){
				if(!is_array($values)){
					$values = [$values];
				}
		
				foreach($values as $value){
					if(strcasecmp($key, "entity")===0){
							
						if(!$entityManager->tableExists($classId)){
							$entityManager->createTable($classId);
						}
						
						$setup->tick();
						
						continue 2;
					}
				}
			}
		}
		
		$state->setStatus(State::FINISHED());
	}
	
}