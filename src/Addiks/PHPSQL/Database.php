<?php
/**
 *
 */

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\DatabaseAdapter\DatabaseAdapterInterface;
use Addiks\PHPSQL\Value\Database\Dsn;
use Addiks\PHPSQL\Value\Database\Dsn\InmemoryDsn;
use Addiks\PHPSQL\Value\Database\Dsn\InternalDsn;
use Addiks\PHPSQL\DatabaseAdapter\InmemoryDatabaseAdapter;
use Addiks\PHPSQL\DatabaseAdapter\InternalDatabaseAdapter;

/**
 *
 */
class Database
{

    public function __construct($dsn, $doInitDatabaseAdapters=true)
    {
        if (!$dsn instanceof Dsn) {
            $dsn = Dsn::factorizeDSN($dsn);
        }
        
        $this->dsn = $dsn;

        if ($doInitDatabaseAdapters) {
            $this->initDatabaseAdapters();
        }
    }

    protected $dsn;

    public function getDsn()
    {
        return $this->dsn;
    }
    
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
    
    public function addDatabaseAdapter(DatabaseAdapterInterface $adapter)
    {
        $type = $adapter->getTypeName();
        $this->databaseAdapters[$type] = $adapter;
    }
    
    public function getAvailableDatabaseTypes()
    {
        return array_keys($this->databaseAdapters);
    }

    public function getFilesystem()
    {
        return $this->getDatabaseAdapter()->getFilesystem();
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
            $result = new TemporaryResult(['adapter']);
            $result->setIsSuccess(true);
            
            foreach ($this->getAvailableDatabaseTypes() as $type) {
                $result->addRow(array($type));
            }
            
        } elseif (substr(trim(strtoupper($statement)), 0, strlen('SET ADAPTER ')) === 'SET ADAPTER ') {
            $result = new TemporaryResult(['adapter']);

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
            /* @var $databaseAdapter DatabaseAdapterInterface */
            $databaseAdapter = $this->getDatabaseAdapter();
            
            $result = $databaseAdapter->query($statement, $parameters);
        }
        
        return $result;
    }

    public function initDatabaseAdapters()
    {
        $this->addDatabaseAdapter(new InmemoryDatabaseAdapter());
        $this->addDatabaseAdapter(new InternalDatabaseAdapter());
    }
}
