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

use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Entity\Job\Statement\DropStatement;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Schema\SchemaManager;

class UseExecutor implements StatementExecutorInterface
{
    
    public function __construct(SchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof DropStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement DropStatement */
        
        $this->schemaManager->setCurrentlyUsedDatabaseId($statement->getDatabase());
        
        $result = new TemporaryResult();
        $result->setIsSuccess($this->schemaManager->getCurrentlyUsedDatabaseId() === $statement->getDatabase());
        
        return $result;
    }
}
