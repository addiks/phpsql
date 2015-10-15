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

use DateTime;
use Addiks\PHPSQL\ValueResolver\FunctionResolver;
use Addiks\PHPSQL\Job\Part\FunctionJob;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\ValueResolver\ValueResolver;

class NowFunction implements FunctionInterface
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
        return (new DateTime("now"))->format("Y-m-d H:i:s");
    }
}
