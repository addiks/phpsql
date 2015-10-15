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

namespace Addiks\PHPSQL\PDO;

use Addiks\PHPSQL\PDO\Statement;
use Addiks\PHPSQL\Value\Database\Dsn\Internal as InternalDSN;
use Addiks\PHPSQL\Value\Database\Dsn\InmemoryDsn;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\Database\DatabaseAdapter\InmemoryDatabaseAdapter;
use Addiks\PHPSQL\Database\DatabaseAdapter\InternalDatabaseAdapter;
use PDO as BasePDO;
use Addiks\PHPSQL\Value\Database\Dsn;

/**
 * Takes place of the original \PDO class in PHP for providing a connection to the internal database.
 * @see \PDO
 */
class PDO extends BasePDO
{
    
    const DRIVERNAME = "internal";
    
    /**
     * Creates a PDO instance representing a connection to a database
     * @link http://www.php.net/manual/en/pdo.construct.php
     * @param dsn
     * @param username
     * @param passwd
     * @param options[optional]
     */
    public function __construct(
        $dsn,
        $username = "",
        $passwd = "",
        $options = array(),
        Database $database = null
    ) {
        if (!$dsn instanceof Dsn) {
            $dsn = Dsn::factorizeDSN($dsn);
        }

        if (is_null($database)) {
            $database = new Database($dsn);
        }

        $this->dsn = $dsn;
        $this->options = $options;
        $this->databaseResource = $database;
        $this->databaseResource->setCurrentDatabaseType($dsn->getDriverName());
    }
    
    /**
     * The PDO options.
     *
     * @var array
     */
    private $options;

    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * The DSN containing the database-id to use for this connection.
     * @see Dsn
     * @var Internal
     */
    private $dsn;

    /**
     * @return Internal
     */
    protected function getDSN()
    {
        return $this->dsn;
    }
    
    /**
     * The database-resource to communicate with the database.
     * @var Database
     */
    private $databaseResource;
        
    /**
     * @return Database
     */
    public function getDatabaseResource()
    {
        return $this->databaseResource;
    }
    
    /**
     * Prepares a statement for execution and returns a statement object
     * @link http://www.php.net/manual/en/pdo.prepare.php
     * @param statement string <p>
     * This must be a valid SQL statement for the target database server.
     * </p>
     * @param driver_options array[optional] <p>
     * This array holds one or more key=&gt;value pairs to set
     * attribute values for the PDOStatement object that this method
     * returns. You would most commonly use this to set the
     * PDO::ATTR_CURSOR value to
     * PDO::CURSOR_SCROLL to request a scrollable cursor.
     * Some drivers have driver specific options that may be set at
     * prepare-time.
     * </p>
     * @return PDOStatement If the database server successfully prepares the statement,
     * PDO::prepare returns a
     * PDOStatement object.
     * If the database server cannot successfully prepare the statement,
     * PDO::prepare returns false or emits
     * PDOException (depending on error handling).
     * </p>
     * <p>
     * Emulated prepared statements does not communicate with the database server
     * so PDO::prepare does not check the statement.
     */
    public function prepare($statement, $driver_options = null)
    {
    
        $statementResource = new Statement($statement, $this);
        
        return $statementResource;
    }
    
    /**
     * Initiates a transaction
     * @link http://www.php.net/manual/en/pdo.begintransaction.php
     * @return bool Returns true on success or false on failure.
     */
    public function beginTransaction()
    {
    }
    
    /**
     * Commits a transaction
     * @link http://www.php.net/manual/en/pdo.commit.php
     * @return bool Returns true on success or false on failure.
     */
    public function commit()
    {
    }
    
    /**
     * Rolls back a transaction
     * @link http://www.php.net/manual/en/pdo.rollback.php
     * @return bool Returns true on success or false on failure.
     */
    public function rollBack()
    {
    }
    
    /**
     * Set an attribute
     * @link http://www.php.net/manual/en/pdo.setattribute.php
     * @param attribute int
     * @param value mixed
     * @return bool Returns true on success or false on failure.
     */
    public function setAttribute($attribute, $value)
    {
    }
    
    /**
     * Execute an SQL statement and return the number of affected rows
     * @link http://www.php.net/manual/en/pdo.exec.php
     * @param statement string <p>
     * The SQL statement to prepare and execute.
     * </p>
     * <p>
     * Data inside the query should be properly escaped.
     * </p>
     * @return int PDO::exec returns the number of rows that were modified
     * or deleted by the SQL statement you issued. If no rows were affected,
     * PDO::exec returns 0.
     * </p>
     * &return.falseproblem;
     * <p>
     * The following example incorrectly relies on the return value of
     * PDO::exec, wherein a statement that affected 0 rows
     * results in a call to die:
     * exec() or die(print_r($db->errorInfo(), true));
     * ?>
     * ]]>
     */
    public function exec($statementString)
    {
        
        $statement = new Statement($statementString, $this);

        $statement->execute();
        
        $this->lastInsertId = $statement->getLastInsertId();
        
        return 0;
    }
    
    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     * @link http://www.php.net/manual/en/pdo.query.php
     * @param statement string <p>
     * The SQL statement to prepare and execute.
     * </p>
     * <p>
     * Data inside the query should be properly escaped.
     * </p>
     * @return PDOStatement PDO::query returns a PDOStatement object, or false
     * on failure.
     */
    public function query($statementString, array $parameters = array())
    {
        
        $statement = new Statement($statementString, $this);
        $statement->execute($parameters);
        
        $this->lastInsertId = $statement->getLastInsertId();
        
        return $statement;
    }
    
    private $lastInsertId;
    
    public function setLastInsetId(array $row)
    {
        $this->lastInsertId = $row;
    }
    
    /**
     * Returns the ID of the last inserted row or sequence value
     * @link http://www.php.net/manual/en/pdo.lastinsertid.php
     * @param name string[optional] <p>
     * Name of the sequence object from which the ID should be returned.
     * </p>
     * @return string If a sequence name was not specified for the name
     * parameter, PDO::lastInsertId returns a
     * string representing the row ID of the last row that was inserted into
     * the database.
     * </p>
     * <p>
     * If a sequence name was specified for the name
     * parameter, PDO::lastInsertId returns a
     * string representing the last value retrieved from the specified sequence
     * object.
     * </p>
     * <p>
     * If the PDO driver does not support this capability,
     * PDO::lastInsertId triggers an
     * IM001 SQLSTATE.
     */
    public function lastInsertId($name = null)
    {
        if (count($this->lastInsertId)<=0) {
            return null;
            
        } elseif (count($this->lastInsertId)===1) {
            return current($this->lastInsertId);
            
        } else {
            return $this->lastInsertId;
        }
    }
    
    /**
     * Fetch the SQLSTATE associated with the last operation on the database handle
     * @link http://www.php.net/manual/en/pdo.errorcode.php
     * @return mixed a SQLSTATE, a five characters alphanumeric identifier defined in
     * the ANSI SQL-92 standard. Briefly, an SQLSTATE consists of a
     * two characters class value followed by a three characters subclass value. A
     * class value of 01 indicates a warning and is accompanied by a return code
     * of SQL_SUCCESS_WITH_INFO. Class values other than '01', except for the
     * class 'IM', indicate an error. The class 'IM' is specific to warnings
     * and errors that derive from the implementation of PDO (or perhaps ODBC,
     * if you're using the ODBC driver) itself. The subclass value '000' in any
     * class indicates that there is no subclass for that SQLSTATE.
     * </p>
     * <p>
     * PDO::errorCode only retrieves error codes for operations
     * performed directly on the database handle. If you create a PDOStatement
     * object through PDO::prepare or
     * PDO::query and invoke an error on the statement
     * handle, PDO::errorCode will not reflect that error.
     * You must call PDOStatement::errorCode to return the error
     * code for an operation performed on a particular statement handle.
     * </p>
     * <p>
     * Returns &null; if no operation has been run on the database handle.
     */
    public function errorCode()
    {
    }
    
    /**
     * Fetch extended error information associated with the last operation on the database handle
     * @link http://www.php.net/manual/en/pdo.errorinfo.php
     * @return array PDO::errorInfo returns an array of error information
     * about the last operation performed by this database handle. The array
     * consists of the following fields:
     * <tr valign="top">
     * <td>Element</td>
     * <td>Information</td>
     * </tr>
     * <tr valign="top">
     * <td>0</td>
     * <td>SQLSTATE error code (a five characters alphanumeric identifier defined
     * in the ANSI SQL standard).</td>
     * </tr>
     * <tr valign="top">
     * <td>1</td>
     * <td>Driver-specific error code.</td>
     * </tr>
     * <tr valign="top">
     * <td>2</td>
     * <td>Driver-specific error message.</td>
     * </tr>
     * </p>
     * <p>
     * If the SQLSTATE error code is not set or there is no driver-specific
     * error, the elements following element 0 will be set to &null;.
     * </p>
     * <p>
     * PDO::errorInfo only retrieves error information for
     * operations performed directly on the database handle. If you create a
     * PDOStatement object through PDO::prepare or
     * PDO::query and invoke an error on the statement
     * handle, PDO::errorInfo will not reflect the error
     * from the statement handle. You must call
     * PDOStatement::errorInfo to return the error
     * information for an operation performed on a particular statement handle.
     */
    public function errorInfo()
    {
    }
    
    /**
     * Retrieve a database connection attribute
     * @link http://www.php.net/manual/en/pdo.getattribute.php
     * @param attribute int <p>
     * One of the PDO::ATTR_* constants. The constants that
     * apply to database connections are as follows:
     * PDO::ATTR_AUTOCOMMIT
     * PDO::ATTR_CASE
     * PDO::ATTR_CLIENT_VERSION
     * PDO::ATTR_CONNECTION_STATUS
     * PDO::ATTR_DRIVER_NAME
     * PDO::ATTR_ERRMODE
     * PDO::ATTR_ORACLE_NULLS
     * PDO::ATTR_PERSISTENT
     * PDO::ATTR_PREFETCH
     * PDO::ATTR_SERVER_INFO
     * PDO::ATTR_SERVER_VERSION
     * PDO::ATTR_TIMEOUT
     * </p>
     * @return mixed A successful call returns the value of the requested PDO attribute.
     * An unsuccessful call returns null.
     */
    public function getAttribute($attribute)
    {
        switch($attribute){
            case \PDO::ATTR_DRIVER_NAME:
                return 'internal';
        }
    }
    
    /**
     * Quotes a string for use in a query.
     * @link http://www.php.net/manual/en/pdo.quote.php
     * @param string string <p>
     * The string to be quoted.
     * </p>
     * @param parameter_type int[optional] <p>
     * Provides a data type hint for drivers that have alternate quoting styles.
     * </p>
     * @return string a quoted string that is theoretically safe to pass into an
     * SQL statement. Returns false if the driver does not support quoting in
     * this way.
     */
    public function quote($string, $parameter_type = null)
    {
        
    }
    
    /**
     * Return an array of available PDO drivers
     * @link http://www.php.net/manual/en/pdo.getavailabledrivers.php
     * @return array PDO::getAvailableDrivers returns an array of PDO driver names. If
     * no drivers are available, it returns an empty array.
     */
    public static function getAvailableDrivers()
    {
        return array_merge(['internal', parent::getAvailableDrivers()]);
    }
}
