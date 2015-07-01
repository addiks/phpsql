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

namespace Addiks\PHPSQL\Entity\Job\Part;

use Addiks\PHPSQL\Value\Enum\Sql\Operator;

use Addiks\PHPSQL\Entity\Job\Part;
use Addiks\Common\Entity;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

class ConditionJob extends Part
{
    
    private $firstParameter;
    
    public function getFirstParameter()
    {
        if (is_null($this->firstParameter)) {
            $this->setFirstParameter(SqlToken::T_TRUE());
        }
        return $this->firstParameter;
    }
    
    public function setFirstParameter($parameter)
    {
        $this->firstParameter = $parameter;
    }
    
    private $lastParameter;
    
    public function getLastParameter()
    {
        if (is_null($this->lastParameter)) {
            $this->setLastParameter(SqlToken::T_TRUE());
        }
        return $this->lastParameter;
    }
    
    public function setLastParameter($parameter)
    {
        $this->lastParameter = $parameter;
    }
    
    private $operator;

    public function setOperator(Operator $operator)
    {
        $this->operator = $operator;
    }
    
    public function getOperator()
    {
        if (is_null($this->operator)) {
            $this->setOperator(Operator::OP_EQUAL());
        }
        return $this->operator;
    }
}
