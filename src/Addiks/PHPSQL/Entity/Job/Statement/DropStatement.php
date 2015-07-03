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

namespace Addiks\PHPSQL\Entity\Job\Statement;

use Addiks\PHPSQL\Entity\Job\Statement;
use Addiks\PHPSQL\Service\Executor\DropExecutor;

/**
 *
 * @Addiks\Statement(executorClass="DropExecutor")
 * @author gerrit
 *
 */
class DropStatement extends Statement
{
    
    private $type;
    
    const TYPE_DATABASE = "database";
    const TYPE_TABLE = "table";
    const TYPE_VIEW = "view";
    
    public function setType($type)
    {
        $type = (string)$type;
        if (!in_array($type, [self::TYPE_DATABASE, self::TYPE_TABLE, self::TYPE_VIEW])) {
            throw new ErrorException("Invalid type '{$type}' given to drop-job!");
        }
        $this->type = $type;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    private $subjects = array();
    
    public function addSubject($subject)
    {
        $this->subjects[] = (string)$subject;
    }
    
    public function getSubjects()
    {
        return $this->subjects;
    }
    
    private $onlyIfExist = false;
    
    public function setOnlyIfExist($bool)
    {
        $this->onlyIfExist = (bool)$bool;
    }
    
    public function getOnlyIfExist()
    {
        return $this->onlyIfExist;
    }
    
    private $isTemporary = false;
    
    public function setIsTemporary($bool)
    {
        $this->isTemporary = (bool)$bool;
    }
    
    public function getIsTemporary()
    {
        return $this->isTemporary;
    }
    
    private $referenceOption;
    
    public function setReferenceOption(ReferenceOption $option)
    {
        $this->referenceOption = $option;
    }
    
    public function getReferenceOption()
    {
        return $this->referenceOption;
    }
    
    public function getResultSpecifier()
    {
    }
}
