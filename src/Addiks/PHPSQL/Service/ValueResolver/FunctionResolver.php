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

namespace Addiks\PHPSQL\Service\ValueResolver;

use Addiks\PHPSQL\Entity\Job\FunctionJob;

abstract class FunctionResolver
{
    
    abstract public function getExpectedParameterCount();
    
    abstract public function executeFunction(FunctionJob $function);
    
    private $valueResolver;
    
    /**
     *
     * @return ValueResolver
     *
     */
    public function getValueResolver()
    {
        if (is_null($this->valueResolver)) {
            /* @var $resolver ValueResolver */
            $this->factorize($resolver);
            
            $this->setValueResolver($resolver);
        }
        return $this->valueResolver;
    }
    
    public function setValueResolver(ValueResolver $resolver)
    {
        $this->valueResolver = $resolver;
    }
}
