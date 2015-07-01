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
 * For-Renderer renders a html-formular-part for entering the database DSN-values.
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Database
 */
class Dsn extends FormElement
{
    
    /**
     * Renders the actual html code.
     * @see Addiks\Common.FormElement::renderFormElement()
     */
    public function renderFormElement($name, $default = null)
    {
        
        return "
		
			<input type=\"hidden\" name=\"{$name}__type\" value=\"mysql\" />
			
			<span>
				<label class=\"alignedLeft\" for=\"{$name}__database\">Database</label>
				<input class=\"alignedRight\" type=\"text\" name=\"{$name}__database\" id=\"{$name}__database\" />
			</span>
			<span>
				<label class=\"alignedLeft\" for=\"{$name}__hostname\">Hostname</label>
				<input class=\"alignedRight\" type=\"text\" name=\"{$name}__hostname\" id=\"{$name}__hostname\" />
			</span>
		";
    }
    
    /**
     * Fetches DSN-value based on given request sent using the generated formular.
     * @see self::renderFormElement()
     * @see Addiks\Common.FormElement::fromRequest()
     */
    public function fromRequest(Request $request, $name)
    {
    
        $type = null;
        if (isset($request->getArguments()["{$name}__type"])) {
            $type = $request->getArguments()["{$name}__type"];
        }
        
        $arguments = $request->getArguments();
        
        switch($type){
            
            default:
            case 'mysql':
                
                if (!isset($arguments["{$name}__database"]) || !isset($arguments["{$name}__hostname"])) {
                    return null;
                }
                
                $database = $arguments["{$name}__database"];
                $hostname = $arguments["{$name}__hostname"];
                
                $database = preg_replace("/[^a-zA-Z0-9#_-]/is", "_", $database);
                
                return Dsn::factory("mysql:dbname={$database};host={$hostname}");
        
        }
    }
}
