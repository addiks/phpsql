<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL\StatementExecutor;

use Addiks\PHPSQL\Entity\Job\StatementJob;

interface StatementExecutorInterface
{
    public function canExecuteJob(StatementJob $statement);

    public function executeJob(StatementJob $statement, array $parameters = array());
}
