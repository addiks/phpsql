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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;

abstract class Executor
{
    
    public function __construct(FilesystemInterface $filesystem, ValueResolver $valueResolver)
    {
        $this->filesystem = $filesystem;
        $this->valueResolver = $valueResolver;
    }

    protected $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    protected $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
    }

    /**
     *
     * @return Result
     * @param Statement $statement
     */
    final public function executeJob(Statement $statement, array $parameters = array())
    {
        
        $statement->validate();
        
        $this->valueResolver->resetParameterCurrentIndex();
        $this->valueResolver->setStatement($statement);
        $this->valueResolver->setStatementParameters($parameters);
        
        $className = get_class($statement);
        
        $return = $this->executeConcreteJob($statement, $parameters);
        
        return $return;
    }
    
    /**
     *
     * @return Result
     * @param Statement $statement
     */
    abstract protected function executeConcreteJob($statement, array $parameters = array());
    
    public function newEmptyResult()
    {
    
        $id = str_replace(" ", "-", microtime())."-".uniqid();
    
        $resultFile = $this->filesystem->getFile("DatabaseResults/{$id}");

        $result = new Result($resultFile);

        return $result;
    }
    
    public function getIndexDoublesStorage($indexName, $tableName)
    {
    
    }
    
    public function getIndexStorage($indexName, $tableName)
    {
    
    }
}
