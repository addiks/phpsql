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

namespace Addiks\Database\Service\RequestHandler;

use Addiks\Workflow\RequestHandler;

/**
 * @Addiks\Request(path="/system/database/schema")
 */
class Schema extends RequestHandler{
	
	/**
	 * @Addiks\Request(method="UPDATE")
	 */
	public function update(){
		
		/* @var $metaDataCache \Addiks\Common\Service\MetaDataCache */
		$this->factorize($metaDataCache);
		
		/* @var $entityManager \Addiks\Database\Resource\EntityManager */
		$this->factorize($entityManager);
		
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
						continue 2;
					}
				}
			}
		}
		
	}
	
}