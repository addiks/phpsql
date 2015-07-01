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

namespace Addiks\Database\Entity\Configuration;

use Addiks\Database\Value\Database\Dsn\Internal;

use Addiks\Database\Value\Database\Dsn;

use Addiks\Common\Entity\Configuration;

/**
 * Configuration for database-connection.
 * Holds DSN (google it!), username and password (if needed).
 * 
 * @adminPanel database
 * @title Database connection
 * @description Please specify how to connect to the database.
 * @icon Addiks/Database/client/images/controlPanel.png
 * 
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Installer
 */
class Database extends Configuration{
	
	/** 
	 * The DSN (Data-Source-Name) contains the database-specifier.
	 * It specifies WHAT database it is, WHERE it is located and HOW it shoeld be connected to.
	 * 
	 * @var Dsn 
	 */
	protected $dsn;
	
	/**
	 * @see self::$dsn
	 * @return Dsn
	 */
	public function getDsn(){
		if(is_null($this->dsn)){
			$this->setDsn(Internal::factory("internal:default"));
		}
		return $this->dsn;
	}
	
	/**
	 * @see self::$dsn
	 * @configurable
	 */
	public function setDsn(Dsn $dsn){
		$this->dsn = $dsn;
	}
	
	/** 
	 * Username used to log-in to the database-server.
	 * (may be not needed)
	 * 
	 * @var Username */
	protected $username;
	
	/**
	 * @see self::$username
	 * @return Username
	 */
	public function getUsername(){
		return $this->username;
	}
	
	/**
	 * @see self::$username
	 * @configurable
	 * @param Username $username
	 */
	public function setUsername(Username $username){
		$this->username = $username;
	}
	
	/** 
	 * Password used to log-in to the database-server.
	 * 
	 * @var Password 
	 */
	protected $password = "";
	
	/**
	 * @see self::$password
	 * @return Password
	 */
	public function getPassword(){
		return $this->password;
	}
	
	/**
	 * @see self::$password
	 * @param Password $password
	 * @configurable
	 */
	public function setPassword(Password $password){
		$this->password = $password;
	}
	
}
