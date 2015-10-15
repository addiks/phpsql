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

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Result\ResultInterface;
use Addiks\PHPSQL\Job\Part\FunctionJob;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\ValueResolver\ValueResolver;

class SumFunction implements FunctionInterface
{
    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }

    private $valueResolver;

    public function executeFunction(
        FunctionJob $function,
        ExecutionContext $context
    ) {
        /* @var $argumentValue Value */
        $argumentValue = current($function->getArguments());
        
        $beforeSourceRow = $context->getCurrentSourceRow();
        
        $sum = 0;
        foreach ($context->getCurrentSourceSet() as $row) {
            $context->setCurrentSourceRow($row);
            
            $value = $this->valueResolver->resolveValue($argumentValue, $context);
            
            if (is_numeric($value)) {
                $sum += $value;
            }
        }

        $context->setCurrentSourceRow($beforeSourceRow);
        
        return $sum;
    }
}
