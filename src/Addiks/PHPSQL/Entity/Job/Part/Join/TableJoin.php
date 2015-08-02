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

namespace Addiks\PHPSQL\Entity\Job\Part\Join;

use Addiks\PHPSQL\Entity\Job\Part;
use Addiks\PHPSQL\Entity\Job\Part\ValuePart;

class TableJoin extends Part
{
    
    private $dataSource;
    
    public function setDataSource($source)
    {
        $this->dataSource = $source;
    }
    
    public function getDataSource()
    {
        return $this->dataSource;
    }
    
    private $isRight = false;
    
    public function setIsRight($bool)
    {
        $this->isRight = (bool)$bool;
    }
    
    public function getIsRight()
    {
        return $this->isRight;
    }
    
    public function setIsLeft($bool)
    {
        $this->isRight = !(bool)$bool;
    }
    
    public function getIsLeft()
    {
        return !$this->isRight;
    }
    
    private $isInner = false;
    
    public function setIsInner($bool)
    {
        $this->isInner = (bool)$bool;
    }
    
    public function getIsInner()
    {
        return $this->isInner;
    }
    
    public function setIsOuter($bool)
    {
        $this->isInner = !(bool)$bool;
    }
    
    public function getIsOuter()
    {
        return !$this->isInner;
    }
    
    private $condition;
    
    public function setCondition(ValuePart $condition)
    {
        $this->condition = $condition;
    }
    
    public function setUsingColumnCondition(Column $column)
    {
        $this->condition = $column;
    }
    
    public function getCondition()
    {
        return $this->condition;
    }
}
