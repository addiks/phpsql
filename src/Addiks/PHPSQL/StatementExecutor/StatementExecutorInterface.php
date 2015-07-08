<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL\StatementExecutor;

interface StatementExecutorInterface
{
    public function executeJob(Statement $statement, array $parameters = array());
}
