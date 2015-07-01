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

namespace Addiks\Database\Service\FormElement\Database;

use Addiks\Installer\FormElement;
use Addiks\Common\Request;

/**
 * Form Renderer renders html form part for database-username
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Database
 */
class Username extends FormElement{
	
	/**
	 * renders html form part for database-username
	 * @see Addiks\Common.FormElement::renderFormElement()
	 */
	public function renderFormElement($name, $default=null){
		return "
			<span>
				<label for=\"{$name}\" class=\"alignedLeft\">Username</label>
				<input type=\"text\" class=\"alignedRight\" name=\"{$name}\" value=\"{$default}\" id=\"{$name}\" />
			</span>
		";
	}
	
	/**
	 * Fetches entered username from request.
	 * @see Addiks\Installer.FormElement::fromRequest()
	 */
	public function fromRequest(Request $request, $name){
		
		if(!isset($request->getArguments()[$name])){
			return null;
		}
		
		$username = $request->getArguments()[$name];
		
		return Username::factory($username);
	}
	
}