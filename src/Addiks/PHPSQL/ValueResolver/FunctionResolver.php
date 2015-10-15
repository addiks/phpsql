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

namespace Addiks\PHPSQL\ValueResolver;

use InvalidArgumentException;
use ErrorException;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Job\Part\FunctionJob;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\ValueResolver\FunctionResolver\FunctionInterface;

class FunctionResolver
{

    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }
    
    protected $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }
    
    public function getExpectedParameterCount()
    {

    }
    
    public function executeFunction(
        FunctionJob $functionJob,
        ExecutionContext $context
    ) {
        $functionName = $functionJob->getName();

        /* @var $functionResolver FunctionResolverInterface */
        $functionResolver = null;

        if (isset($this->functionOverrides[$functionName])) {
            $functionResolver = $this->functionOverrides[$functionName];

        } else {
            $functionName = preg_replace("/[^a-zA-Z0-9_]/is", "", $functionName);
            $functionResolverClassName = ucfirst(strtolower($functionName))."Function";
            $functionResolverClass = "Addiks\PHPSQL\ValueResolver\FunctionResolver\\{$functionResolverClassName}";
            # TODO: This few lines above are bad design, change it!

            if (!class_exists($functionResolverClass)) {
                throw new InvalidArgumentException(
                    "Function '{$functionName}' does not exist! ({$functionResolverClass})"
                );
            }
            if (!is_subclass_of($functionResolverClass, FunctionInterface::class)) {
                throw new ErrorException("Function '{$functionName}' does not implement FunctionInterface!");
            }

            $functionResolver = new $functionResolverClass($this->valueResolver);
        }

        $returnValue = $functionResolver->executeFunction(
            $functionJob,
            $context
        );

        return $returnValue;
    }

    protected $functionOverrides = array();

    public function setFunctionOverride($functionName, FunctionResolverInterface $functionResolver)
    {
        $this->functionOverrides[$functionName] = $functionResolver;
    }

    public function getFunctionOverrides()
    {
        return $this->functionOverrides;
    }

    public function clearFunctionOverrides()
    {
        $this->functionOverrides = array();
    }
}
