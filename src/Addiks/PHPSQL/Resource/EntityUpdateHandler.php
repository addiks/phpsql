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

namespace Addiks\Database\Resource;

use Addiks\Common\Annotation;

use Addiks\Common\Resource;

use Addiks\Common\Resource\AnnotationHandlerInterface;

use Addiks\Common\Resource;

/**
 * @Addiks\AnnotationHandler(listenKey="Entity")
 * @Addiks\AnnotationHandler(listenKey="Entity")
 */
class EntityUpdateHandler extends Resource implements AnnotationHandlerInterface{
	
	public function onClassAnnotationAdded($classId, Annotation $annotation){
		
		/* @var $entityManager \Addiks\Database\Resource\EntityManager */
		$this->factorize($entityManager);
		
		if(!$entityManager->tableExists($classId)){
			$entityManager->createTable($classId);
		}
	}
	
	public function onClassAnnotationRemoved($classId, Annotation $annotation){
	
		/* @var $entityManager \Addiks\Database\Resource\EntityManager */
		$this->factorize($entityManager);
	
		if($entityManager->tableExists($classId)){
			# TODO: remove table... how? figure out later
		}
	}
	
	public function onClassMemberAnnotationAdded($classId, $memberName, Annotation $annotation){
		# TODO: implement alter table
	}
	
	public function onClassMemberAnnotationRemoved($classId, $memberName, Annotation $annotation){
		# TODO: implement alter table
	}
	
	public function flushChanges(){
		# TODO: implement queue instead of executing immedeatly
	}
	
	public function onFileAnnotationAdded($filePath, Annotation $annotation){
	}
	
	public function onFileAnnotationRemoved($filePath, Annotation $annotation){
	}
	
	public function onClassMethodAnnotationAdded($classId, $methodName, Annotation $annotation){
	}
	
	public function onClassMethodAnnotationRemoved($classId, $methodName, Annotation $annotation){
	}
	
	public function onClassConstantAnnotationAdded($classId, $constantName, Annotation $annotation){
	}
	
	public function onClassConstantAnnotationRemoved($classId, $constantName, Annotation $annotation){
	}
}