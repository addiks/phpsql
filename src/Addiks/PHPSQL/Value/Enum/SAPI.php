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

namespace Addiks\PHPSQL\Value\Enum;

use Addiks\PHPSQL\Value\Enum;

/**
 * Enum for PHP-SAPI.
 * 
 * @see php_sapi_name
 */
class SAPI extends Enum{
	
	protected function __construct($value){
		parent::__construct(strtoupper($value));
	}
	
	const AOLSERVER = "aolserver";
	
	const APACHE = "apache";
	
	const APACHE2FILTER = "apache2filter";
	
	const APACHE2HANDLER = "apache2handler";
	
	const CAUDIUM = "caudium";
	
	const CGI = "cgi";
	
	const CGI_FCGI = "cgi-fcgi";
	
	const CLI = "cli";
	
	const CONTINUITY = "continuity";
	
	const EMBED = "embed";
	
	const ISAPI = "isapi";
	
	const LITESPEED = "litespeed";
	
	const MILTER = "milter";
	
	const NSAPI = "nsapi";
	
	const PHTTPD = "phttpd";
	
	const PI3WEB = "pi3web";
	
	const ROXEN = "roxen";
	
	const THTTPD = "thttpd";
	
	const TUX = "tux";
	
	const WEBJAMES = "webjames";
	
}