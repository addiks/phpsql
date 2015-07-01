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

namespace Addiks\PHPSQL\Resource;

use Addiks\PHPSQL\Entity\Configuration\Database;
use Addiks\PHPSQL\Value\Database\Dsn;
use Addiks\PHPSQL\Resource\PDO\Internal;
use Addiks\PHPSQL\Resource\Storages;

use Addiks\Common\Value\Text\Directory\Data;
use Addiks\Common\Resource;

use PDO;

/**
 * class for managing a connection to a database
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Database
 */
class Connection extends Resource
{
    
    public static function newFromDsn(Dsn $dsn)
    {
        $config = new Database();
        $config->setDsn($dsn);

        $connection = new self();
        $connection->setDatabaseConnectionConfig($config);

        return $connection;
    }

    private $config;
    
    public function setDatabaseConnectionConfig(Database $config)
    {
        $this->config = $config;
    }
    
    public function getDatabaseConnectionConfig()
    {
        if (is_null($this->config)) {
            /* @var $config Database */
            $this->factorize($config);
            
            $this->setDatabaseConnectionConfig($config);
        }
        return $this->config;
    }
    
    public function hasConfiguration()
    {
        
        $configuration = $this->getDatabaseConnectionConfig();
        
        $dsn = $configuration->getDsn();
        
        return $dsn instanceof Dsn;
    }
    
    /**
     * Php Database Object. see: php.net/pdo
     * @var PDO
     */
    protected $pdo;
    
    /**
     * Gets the PHP Data Object.
     * @see http://php.net/pdo
     * @see self::connect()
     * @return PDO
     */
    public function getPDO()
    {
        if (is_null($this->pdo)) {
            $this->connect();
        }
        return $this->pdo;
    }

    public function isConnected()
    {
        return !is_null($this->pdo);
    }
    
    public function query($statement, array $parameters = array())
    {
        
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->getPDO()->query($statement, $parameters);
        
    }
    
    /**
     * tries to cennect to database using configuration.
     *
     * @see self::getDatabaseConnectionConfig()
     * @throws \InvalidArgumentException
     */
    public function connect()
    {
        
        $configuration = $this->getDatabaseConnectionConfig();
        
        $username = $configuration->getUsername();
        $password = $configuration->getPassword();
        $dsn      = $configuration->getDsn();
        
        if (!$dsn instanceof Dsn) {
            throw new InvalidArgumentException("No DSN provided to connect to database with! (no database configured?)");
        }
        
        switch(strtolower($dsn->getDriverName())){
            
            case Internal::DRIVERNAME:
                
                /* @var $pdoReplacement Internal */
                $this->factorize($pdoReplacement);
                
                $this->injectDepency($pdoReplacement);
                
                $this->pdo = $pdoReplacement;
                break;
            
            case 'cubrid':
            case 'sybase':
            case 'mssql':
            case 'dblib':
            case 'firebird':
            case 'ibm':
            case 'informix':
            case 'mysql':
            case 'oci':
            case 'odbc':
            case 'pgsql':
            case 'sqlite':
            case 'sqlsrv':
            case '4d':
                $this->pdo = new PDO((string)$dsn, (string)$username, (string)$password);
                break;
            
            default:
                throw new InvalidArgumentException("Invalid database-driver '{$dsn->getDriverName()}' given!");
        }
        
        $cache = new ApcCache();
        
        return true;
    }
}
