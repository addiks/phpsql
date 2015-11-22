<?php
/**
 *
 */

namespace Addiks\PHPSQL\Database;

use Addiks\PHPSQL\Result\Temporary;
use Addiks\PHPSQL\Result\ResultInterface;
use Addiks\PHPSQL\Database\Database\DatabaseAdapterDatabaseAdapterInterface;
use Addiks\PHPSQL\Value\Database\Dsn;
use Addiks\PHPSQL\Value\Database\Dsn\InmemoryDsn;
use Addiks\PHPSQL\Value\Database\Dsn\InternalDsn;
use Addiks\PHPSQL\Database\DatabaseAdapter\InmemoryDatabaseAdapter;
use Addiks\PHPSQL\Database\DatabaseAdapter\InternalDatabaseAdapter;
use Addiks\PHPSQL\Database\DatabaseAdapter\DatabaseAdapterInterface;
use Addiks\PHPSQL\Job\StatementJob;

/**
 *
 */
class Database
{

    public function __construct($dsn, $doInitDatabaseAdapters = true)
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

    public function queryStatement(StatementJob $statement, array $parameters = array())
    {
        /* @var $databaseAdapter DatabaseAdapterInterface */
        $databaseAdapter = $this->getDatabaseAdapter();

        $result = $databaseAdapter->queryStatement($statement, $parameters);

        return $result;
    }

    public function prepare($statementString)
    {
        /* @var $databaseAdapter DatabaseAdapterInterface */
        $databaseAdapter = $this->getDatabaseAdapter();

        /* @var $statement StatementJob */
        $statement = $databaseAdapter->prepare($statementString);

        return $statement;
    }

    public function initDatabaseAdapters()
    {
        $this->addDatabaseAdapter(new InmemoryDatabaseAdapter());
        $this->addDatabaseAdapter(new InternalDatabaseAdapter());
    }
}
