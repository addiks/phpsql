<?php
/**
 *
 */

namespace Addiks\PHPSQL\Resource;

use Addiks\Common\Resource;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Resource\DatabaseAdapter\AbstractDatabaseAdapter;
use Addiks\PHPSQL\Entity\Result\ResultInterface;

/**
 *
 */
class Database extends Resource
{
    
    private $currentDatabaseType = 'internal';
    
    public function getCurrentDatabaseType()
    {
        return $this->currentDatabaseType;
    }
    
    public function setCurrentDatabaseType($currentDatabaseType)
    {
        $this->currentDatabaseType = $currentDatabaseType;
    }
    
    private $databaseAdapters = array();
    
    public function getDatabaseAdapter($type = null)
    {
        
        if (is_null($type)) {
            $type = $this->getCurrentDatabaseType();
        }
        
        $adapter = null;
        
        if (isset($this->databaseAdapters[$type])) {
            $adapter = $this->databaseAdapters[$type];
        }
        
        return $adapter;
    }
    
    public function addDatabaseAdapter(AbstractDatabaseAdapter $adapter)
    {
        
        $type = $adapter->getTypeName();
        
        $this->databaseAdapters[$type] = $adapter;
    }
    
    public function getAvailableDatabaseTypes()
    {
        return array_keys($this->databaseAdapters);
    }
    
    /**
     *
     * @param string $statement
     * @return ResultInterface
     */
    public function query($statement, array $parameters)
    {
        
        $result = null;
        
        if (trim(strtoupper($statement)) === 'SHOW ADAPTERS') {
            /* @var $result Temporary */
            $this->factorize($result, [['adapter']]);
            $result->setIsSuccess(true);
            
            foreach ($this->getAvailableDatabaseTypes() as $type) {
                $result->addRow(array($type));
            }
            
        } elseif (substr(trim(strtoupper($statement)), 0, strlen('SET ADAPTER ')) === 'SET ADAPTER ') {
            /* @var $result Temporary */
            $this->factorize($result, [['adapter']]);

            $statement = trim(strtoupper($statement));
            
            $type = substr($statement, strlen('SET ADAPTER '));
            $type = strtolower($type);
             
            if (in_array($type, $this->getAvailableDatabaseTypes())) {
                $this->setCurrentDatabaseType($type);
                $result->setIsSuccess(true);
            } else {
                $result->setIsSuccess(false);
            }
            
        } else {
            /* @var $databaseAdapter AbstractDatabaseAdapter */
            $databaseAdapter = $this->getDatabaseAdapter();
            
            $result = $databaseAdapter->query($statement, $parameters);
        }
        
        return $result;
    }
}
