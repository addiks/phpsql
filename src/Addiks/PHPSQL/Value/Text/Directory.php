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

namespace Addiks\PHPSQL\Value\Text;

use ErrorException;
use Addiks\PHPSQL\Value\Text;

/**
 * Value-Object representing a directory.
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 */
class Directory extends Text{
	
	/**
	 * Checks given directory for existance.
	 * @param unknown_type $directory
	 * @throws \InvalidArgumentException
	 */
	protected function validate($directory){
		
		parent::validate($directory);
		
		if(!is_dir($directory)){
			throw new ErrorException("'{$directory}' is not directory!");
		}
		
	}
	
}
