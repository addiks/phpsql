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

namespace Addiks\PHPSQL\StatementExecutor;

use Addiks\PHPSQL\Entity\Job\Statement\Select as SelectStatement;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\SelectResult;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\Entity\Job\StatementJob;

class SelectExecutor implements StatementExecutorInterface
{
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof SelectStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement SelectStatement */
        
        $result = new SelectResult($statement, $parameters);

        return $result;
    }
}
