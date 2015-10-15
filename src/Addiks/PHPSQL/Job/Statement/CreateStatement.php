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

namespace Addiks\PHPSQL\Job\Statement;

use Addiks\PHPSQL\Job\StatementJob;

abstract class CreateStatement extends StatementJob
{
    
    protected $name;
    
    public function setName($name)
    {
        $this->name = $name;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    protected $ifNotExist = false;
    
    public function setIfNotExists($ifNotExist)
    {
        $this->ifNotExist = (bool)$ifNotExist;
    }
    
    public function getIfNotExists()
    {
        return $this->ifNotExist;
    }
    
    public function getResultSpecifier()
    {
    }
}
