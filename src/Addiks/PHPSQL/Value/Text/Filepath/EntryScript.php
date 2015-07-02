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

namespace Addiks\PHPSQL\Value\Text\Filepath;

use Addiks\PHPSQL\Value\Text\Filepath;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Depencies\Resource\Context;

/**
 * 
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 * @Addiks\Factory(method="self::factoryFromDIC")
 * @Addiks\Singleton
 */
class EntryScript extends Filepath{
	
	const SHELL_SCRIPTNAME = "runshell.php";
	
	static public function factoryFromDIC(Context $context){
		
		/* @var $dataDir \Addiks\PHPSQL\Value\Text\Directory\Data */
		$context->factorize($dataDir);
		
		$entryScript = "{$dataDir}/".self::SHELL_SCRIPTNAME;
		
		if(!file_exists($entryScript)){
			$context->getFramework()->tryWriteDataShellScript();
		}
		
		return static::factory($entryScript);
	}
	
	protected function validate($value){
		
		if(!file_exists($value)){
			throw new InvalidValue("Entry-Script '{$value}' does not exist!");
		}
		
	}
	
}