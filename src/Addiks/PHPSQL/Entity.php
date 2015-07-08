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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Entity\EntityTrait;

/**
 * class for all entities in system.
 *
 * <p>
 * Entities are objects representing actors on application logic.
 * Many entities can be saved in the database.
 * For that, look for the annotation "@ORM\Entity".
 * Every entity has its own identity (unlike value-objects) and can be identified using it's ID (self::$id).
 * (This id however does not automaticly get used when storing in a database.)
 * Entities can provide validation logic.
 * </p>
 *
 * @see Value
 * @see Resource
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 *
 * @MappedSuperclass
 * @Addiks\Singleton(negated=true)
 */
abstract class Entity{

	/**
	 * The identification of this entity.
	 * @var int
	 */
	private $id;
	
	/**
	 * Gets the id of this entity.
	 * @return string
	 */
	public function getId(){
		return $this->id;
	}
	
	public function setId($id){
		$this->id = (int)$id;
	}
	
	/**
	 * The date that this entity was created.
	 * Format: "Y-m-d H:i:s"
	 * @see self::__construct()
	 * @Column(type="string")
	 * @var string
	 */
	private $createdDate;
	
	/**
	 * The date this entity was last modified.
	 * Format: "Y-m-d H:i:s"
	 * @see self::__construct()
	 * @Column(type="string")
	 * @var string
	 */
	private $modifiedDate;
	
	/**
	 * Gets the create-date of this entity.
	 * Format: Y-m-d H:i:s
	 * @return string
	 */
	public function getCreatedDate(){
		return $this->createdDate;
	}
	
	/**
	 * Gets the modified-date of this entity.
	 * Format: Y-m-d H:i:s
	 * @return string
	 */
	public function getModifiedDate(){
		return $this->modifiedDate;
	}
	
	/**
	 * Constructor.
	 * Sets id, create- and modified-date.
	 * @see uuid()
	 */
	public function __construct(){
	
		$this->createdDate = date("Y-m-d H:i:s", time());
	
		$this->modifiedDate = date("Y-m-d H:i:s", time());
	
		$this->id = uniqid();
	
	}
}
