<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\StatementExecutor;

use Addiks\PHPSQL\StatementExecutor\StatementExecutorInterface;
use Addiks\PHPSQL\Job\Statement\StartTransactionStatement;
use Addiks\PHPSQL\Iterators\TransactionalInterface;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Job\Statement\CommitStatement;
use Addiks\PHPSQL\Job\Statement\RollbackStatement;

class TransactionExecutor implements StatementExecutorInterface
{

    public function __construct(
        TransactionalInterface $transactional
    ) {
        $this->transactional = $transactional;
    }

    protected $transactional;

    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof StartTransactionStatement
            || $statement instanceof RollbackStatement
            || $statement instanceof CommitStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $transactional TransactionalInterface */
        $transactional = $this->transactional;

        if ($statement instanceof StartTransactionStatement) {
            $transactional->beginTransaction();

        } elseif ($statement instanceof CommitStatement) {
            $transactional->commit();

        } elseif ($statement instanceof RollbackStatement) {
            $transactional->rollback();
        }
    }
}
