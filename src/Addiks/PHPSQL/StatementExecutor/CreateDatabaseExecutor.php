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

use Addiks\PHPSQL\StatementExecutor;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;

class CreateDatabaseExecutor implements StatementExecutorInterface
{
    
    public function __construct(
        ValueResolver $valueResolver,
        SchemaManager $schemaManager
    ) {
        $this->schemaManager = $schemaManager;
        $this->valueResolver = $valueResolver;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }
    
    protected $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Database */
        
        $name = $this->valueResolver->resolveValue($statement->getName());
        
        $this->schemaManager->createSchema($name);
        
        ### CREATE RESULTSET
        
        $result = new TemporaryResult();
        $result->setIsSuccess($this->schemaManager->schemaExists($name));
        
        return $result;
    }
}
