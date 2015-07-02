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

namespace Addiks\PHPSQL\Service;

use Addiks\PHPSQL\Resource\StoragesProxyTrait;
use Addiks\PHPSQL\Entity\Job\Statement;
use Addiks\PHPSQL\Service\ValueResolver;

abstract class Executor
{
    
    use StoragesProxyTrait;
    
    /**
     *
     * @return Result
     * @param Statement $statement
     */
    final public function executeJob(Statement $statement, array $parameters = array())
    {
        
        $statement->validate();
        
        /* @var $valueResolver ValueResolver */
        $this->factorize($valueResolver);
        
        $valueResolver->resetParameterCurrentIndex();
        $valueResolver->setStatement($statement);
        $valueResolver->setStatementParameters($parameters);
        
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
    
        /* @var $caches \Addiks\Common\Resource\Caches */
        $this->factorize($caches);
    
        $id = str_replace(" ", "-", microtime())."-".uniqid();
    
        $cacheStorage = $caches->acquireCache("DatabaseResults/{$id}");
    
        /* @var $result Result */
        $this->factorize($result, [$cacheStorage]);
    
        return $result;
    }
    
    public function Specifier($resultId)
    {
    
        /* @var $resultSchemaStorage \Addiks\PHPSQL\Entity\Storage */
        $resultSchemaStorage = $this->getResultSchemataStorage($resultId);
        
        /* @var $resultSchema TableSchema */
        $this->factorize($resultSchema, [$resultSchemaStorage]);
    
        return $resultSchema;
    }
    
    public function getIndexDoublesStorage($indexName, $tableName)
    {
    
    }
    
    public function getIndexStorage($indexName, $tableName)
    {
    
    }
}
