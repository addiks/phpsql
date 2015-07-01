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

namespace Addiks\PHPSQL\Entity\Job\Part\FlowControl;

use Addiks\PHPSQL\Entity\Job\Part;

class CaseData extends Part
{
    
    private $caseValue;
    
    public function setCaseValue($caseValue)
    {
        $this->caseValue = $caseValue;
    }
    
    public function getCaseValue()
    {
        return $this->caseValue;
    }
    
    private $whenThenStatements = array();
    
    public function addWhenThenStatement($whenValue, $thenStatement)
    {
        $this->whenThenStatements[] = ['when' => $whenValue, 'then' => $thenStatement];
    }
    
    public function getWhenThenStatements()
    {
        return $this->whenThenStatements;
    }
    
    private $elseStatement;
    
    public function setElseStatement($elseStatement)
    {
        $this->elseStatement = $elseStatement;
    }
    
    public function getElseStatement()
    {
        return $this->elseStatement;
    }
}
