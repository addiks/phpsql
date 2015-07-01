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

namespace Addiks\PHPSQL\Service\FormElement\Database;

use Addiks\Installer\FormElement;
use Addiks\Common\Request;

/**
 * Form-Renderer renders html form for database-password.
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Database
 */
class Password extends FormElement
{
    
    /**
     * Renders html form for database-password.
     * @see Addiks\Common.FormElement::renderFormElement()
     */
    public function renderFormElement($name, $default = null)
    {
        return "
			<span>
				<label class=\"alignedLeft\" for=\"database_password\">Password</label>
				<input class=\"alignedRight\" type=\"password\" name=\"{$name}\" value=\"{$default}\" id=\"database_password\" />
			</span>
		";
    }
    
    /**
     * Fetches password from request.
     * @see Addiks\Installer.FormElement::fromRequest()
     */
    public function fromRequest(Request $request, $name)
    {
        
        if (!isset($request->getArguments()[$name])) {
            return null;
        }
        
        $password = $request->getArguments()[$name];
        
        return Password::factory($password);
    }
}
