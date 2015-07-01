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

namespace Addiks\Database\Entity;

use Addiks\Common\Value\Text\Filepath;

use Addiks\Common\Entity;

class Mount extends Entity{
	
	private $type;
	
	public function setType($type){
		$this->type = $type;
	}
	
	public function getType(){
		return $this->type;
	}
	
	private $source;
	
	public function setSource($source){
		$this->source = $source;
	}
	
	public function getSource(){
		return $this->source;
	}
	
	private $mountPoint;
	
	public function setMountPoint($mountPoint){
		$this->mountPoint = $mountPoint;
	}
	
	public function getMountPoint(){
		return $this->mountPoint;
	}
	
	private $options = array();
	
	public function setOptions(array $options){
		$this->options = $options;
	}
	
	public function addOptions(array $options){
		$this->options = array_merge($this->options, $options);
	}
	
	public function addOption($option){
		$this->options[] = $option;
	}
	
	public function getOptions(){
		return $this->options;
	}
	
	private $dump = 0;
	
	public function setDump($int){
		$this->dump = (int)$int;
	}
	
	public function getDump(){
		return $this->dump;
	}
	
	private $pass = 0;
	
	public function setPass($int){
		$this->pass = (int)$int;
	}
	
	public function getPass(){
		return $this->pass;
	}
	
	public function getIsBind(){
		return $this->type === 'none' && in_array('bind', $this->getOptions());
	}
}