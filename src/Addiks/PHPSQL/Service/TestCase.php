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

namespace Addiks\Database\Service;

class TestCase extends \Addiks\Tests\TestCase{
	
	protected function getDatabaseResource(){
		
		/* @var $fakedDIC \Addiks\Protocol\Resource\Context */
		$contextMock = $this->getMockedContext();
		
		/* @var $database \Addiks\Database\Resource\Database */
		$database = $contextMock->factory("\Addiks\Database\Resource\Database");
		
		return $database;
	}
	
	protected function setUp(){
		
		parent::setUp();
		
		$this->getDatabaseResource()->query("SHOW DATABASES");
	}
}