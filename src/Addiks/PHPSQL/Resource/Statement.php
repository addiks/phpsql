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

namespace Addiks\Database\Resource;

use Addiks\Database\Entity\Result\Temporary;

use Addiks\Database\Resource\PDO\Internal;

use Addiks\Common\Resource;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Database\Entity\Result\ResultInterface;
use Addiks\Database\Resource\Database;

/**
 * 
 * @author gerrit
 * @Addiks\Singleton(negated=true)
 */
class Statement extends Resource{
	
	public function __construct($statementString){
		$this->setStatementString($statementString);
	}
	
	private $statementString;
	
	protected function setStatementString($statement){
		$this->statementString = $statement;
	}
	
	protected function getStatementString(){
		return $this->statementString;
	}
	
	private $result;
	
	protected function getResult(){
		if(is_null($this->result)){
			return new Temporary();
		}
		return $this->result;
	}
	
	protected function setResult(ResultInterface $result){
		$this->result = $result;
	}
	
	public function getLastInsertId(){
		return $this->getResult()->getLastInsertId();
	}
	
	private $boundColumns = array();
	
	protected function getBoundColumns(){
		return $this->boundColumns;
	}
	
	/**
	 * Bind a column to a PHP variable
	 *
	 * @param mixed $column		Number of the column (1-indexed) or name of the column in the result set.
	 *							 If using the column name, be aware that the name should match
	 *							 the case of the column, as returned by the driver.
	 * @param string $param		Name of the PHP variable to which the column will be bound.
	 * @param integer $type		Data type of the parameter, specified by the Core::PARAM_* constants.
	 * @return boolean			 Returns TRUE on success or FALSE on failure
	 */
	public function bindColumn($column, $param, $type = null){
		switch($type){
			
			case \PDO::PARAM_BOOL:
			case \PDO::PARAM_INPUT_OUTPUT:
			case \PDO::PARAM_INT:
			case \PDO::PARAM_LOB:
			case \PDO::PARAM_NULL:
			case \PDO::PARAM_STMT:
			case \PDO::PARAM_STR:
				$this->boundColumns[] = [(string)$column, $param, $type];
				break;
				
			default:
				throw new Exception("Invalid data-type '{$type}'! Must be one of \PDO::PARAM_* constants.");
		}
	}

	private $boundValues = array();
	
	protected function getBoundValues(){
		return $this->boundValues;
	}
	
	/**
	 * Binds a value to a corresponding named or question mark 
	 * placeholder in the SQL statement that was use to prepare the statement.
	 *
	 * @param mixed $param		 Parameter identifier. For a prepared statement using named placeholders,
	 *							 this will be a parameter name of the form :name. For a prepared statement
	 *							 using question mark placeholders, this will be the 1-indexed position of the parameter
	 *
	 * @param mixed $value		 The value to bind to the parameter.
	 * @param integer $type		Explicit data type for the parameter using the Core::PARAM_* constants.
	 *
	 * @return boolean			 Returns TRUE on success or FALSE on failure.
	 */
	public function bindValue($param, $value, $type = null){
		
		if(is_numeric($param)){
			$param--;
		}
		
		switch($type){
		
			case \PDO::PARAM_BOOL:
			case \PDO::PARAM_INPUT_OUTPUT:
			case \PDO::PARAM_INT:
			case \PDO::PARAM_LOB:
			case \PDO::PARAM_NULL:
			case \PDO::PARAM_STMT:
			case \PDO::PARAM_STR:
				$this->boundValues[] = [(string)$value, $param, $type];
				break;
				
			default:
				throw new Exception("Invalid data-type '{$type}'! Must be one of \PDO::PARAM_* constants.");
		}
	}

	private $boundParams = array();
	
	protected function getBoundParams(){
		return $this->boundParams;
	}
	
	/**
	 * Binds a PHP variable to a corresponding named or question mark placeholder in the 
	 * SQL statement that was use to prepare the statement. Unlike Interface->bindValue(),
	 * the variable is bound as a reference and will only be evaluated at the time 
	 * that Interface->execute() is called.
	 *
	 * Most parameters are input parameters, that is, parameters that are 
	 * used in a read-only fashion to build up the query. Some drivers support the invocation 
	 * of stored procedures that return data as output parameters, and some also as input/output
	 * parameters that both send in data and are updated to receive it.
	 *
	 * @param mixed $param		 Parameter identifier. For a prepared statement using named placeholders,
	 *							 this will be a parameter name of the form :name. For a prepared statement
	 *							 using question mark placeholders, this will be the 1-indexed position of the parameter
	 *
	 * @param mixed $variable	  Name of the PHP variable to bind to the SQL statement parameter.
	 *
	 * @param integer $type		Explicit data type for the parameter using the Core::PARAM_* constants. To return
	 *							 an INOUT parameter from a stored procedure, use the bitwise OR operator to set the
	 *							 Core::PARAM_INPUT_OUTPUT bits for the data_type parameter.
	 *
	 * @param integer $length	  Length of the data type. To indicate that a parameter is an OUT parameter
	 *							 from a stored procedure, you must explicitly set the length.
	 * @param mixed $driverOptions
	 * @return boolean			 Returns TRUE on success or FALSE on failure.
	 */
	public function bindParam($column, &$variable, $type = null, $length = null, $driverOptions = array()){
		switch($type){
		
			case \PDO::PARAM_BOOL:
			case \PDO::PARAM_INPUT_OUTPUT:
			case \PDO::PARAM_INT:
			case \PDO::PARAM_LOB:
			case \PDO::PARAM_NULL:
			case \PDO::PARAM_STMT:
			case \PDO::PARAM_STR:
				$this->boundParams[] = [$column, $param, $type];
				break;
		
			default:
				throw new Exception("Invalid data-type '{$type}'! Must be one of \PDO::PARAM_* constants.");
		}
	}

	/**
	 * Closes the cursor, enabling the statement to be executed again.
	 *
	 * @return boolean			 Returns TRUE on success or FALSE on failure.
	 */
	public function closeCursor(){
		$this->result = null;
		return true;
	}

	/** 
	 * Returns the number of columns in the result set 
	 *
	 * @return integer			 Returns the number of columns in the result set represented
	 *							 by the Interface object. If there is no result set,
	 *							 this method should return 0.
	 */
	public function columnCount(){
		return $this->getResult()->count();
	}

	/**
	 * Fetch the SQLSTATE associated with the last operation on the statement handle 
	 *
	 * @see Interface::errorCode()
	 * @return string	  error code string
	 */
	public function errorCode(){
		
	}

	/**
	 * Fetch extended error information associated with the last operation on the statement handle
	 *
	 * @see Interface::errorInfo()
	 * @return array		error info array
	 */
	public function errorInfo(){
		
	}

	/**
	 * Executes a prepared statement
	 *
	 * If the prepared statement included parameter markers, you must either:
	 * call PDOStatement->bindParam() to bind PHP variables to the parameter markers:
	 * bound variables pass their value as input and receive the output value,
	 * if any, of their associated parameter markers or pass an array of input-only
	 * parameter values
	 *
	 *
	 * @param array $params			An array of values with as many elements as there are
	 *								 bound parameters in the SQL statement being executed.
	 * @return boolean				 Returns TRUE on success or FALSE on failure.
	 */
	public function execute($parameters = array()){
		
		/* @var $databaseResource Database */
		$this->factorize($databaseResource);
		
		foreach($this->getBoundValues() as $data){
			list($value, $index, $type) = $data;
			
			$parameters[$index] = $value;
		}
		
		foreach($this->getBoundColumns() as $data){
			// TODO: implement!
		}
		
		foreach($this->getBoundParams() as $data){
			// TODO: implement!
		}
		
		/* @var $result ResultInterface */
		$result = $databaseResource->query($this->getStatementString(), $parameters);
		
		if(is_null($result)){
			return true;
		}
		
		$this->setResult($result);
		
		/* @var $pdo Internal */
		$this->factorize($pdo);
		
		$pdo->setLastInsetId($result->getLastInsertId());
		
		return $result->getIsSuccess();
	}

	/**
	 * fetch
	 *
	 * @see Core::FETCH_* constants
	 * @param integer $fetchStyle		  Controls how the next row will be returned to the caller.
	 *									 This value must be one of the Core::FETCH_* constants,
	 *									 defaulting to Core::FETCH_BOTH
	 *
	 * @param integer $cursorOrientation	For a PDOStatement object representing a scrollable cursor, 
	 *									 this value determines which row will be returned to the caller. 
	 *									 This value must be one of the Core::FETCH_ORI_* constants, defaulting to
	 *									 Core::FETCH_ORI_NEXT. To request a scrollable cursor for your 
	 *									 Interface object,
	 *									 you must set the Core::ATTR_CURSOR attribute to Core::CURSOR_SCROLL when you
	 *									 prepare the SQL statement with Interface->prepare().
	 *
	 * @param integer $cursorOffset		For a Interface object representing a scrollable cursor for which the
	 *									 $cursorOrientation parameter is set to Core::FETCH_ORI_ABS, this value specifies
	 *									 the absolute number of the row in the result set that shall be fetched.
	 *									 
	 *									 For a Interface object representing a scrollable cursor for 
	 *									 which the $cursorOrientation parameter is set to Core::FETCH_ORI_REL, this value 
	 *									 specifies the row to fetch relative to the cursor position before 
	 *									 Interface->fetch() was called.
	 *
	 * @return mixed
	 */
	public function fetch($fetchStyle = \PDO::FETCH_BOTH,
						 $cursorOrientation = \PDO::FETCH_ORI_NEXT,
						 $cursorOffset = null){
		
		/* @var $result Interface */
		$result = $this->getResult();
						 	
		if(is_int($cursorOffset)){
			$result->seek($cursorOffset);
		}
		
		switch($fetchStyle){
				
			case \PDO::FETCH_NUM:
				return $result->fetchRow();
				
			case \PDO::FETCH_ASSOC:
				return $result->fetchAssoc();
				
			case \PDO::FETCH_BOTH:
				return $result->fetchArray();
				
			case \PDO::FETCH_OBJ:
				$assoc = $result->fetchAssoc();
				$object = new _stdClass();
				foreach($assoc as $key => $value){
					$object->$key = $value;
				}
				return $object;
		}
 	}

	/**
	 * Returns an array containing all of the result set rows
	 *
	 * @param integer $fetchStyle		  Controls how the next row will be returned to the caller.
	 *									 This value must be one of the Core::FETCH_* constants,
	 *									 defaulting to Core::FETCH_BOTH
	 *
	 * @param integer $columnIndex		 Returns the indicated 0-indexed column when the value of $fetchStyle is
	 *									 Core::FETCH_COLUMN. Defaults to 0.
	 *
	 * @return array
	 */
	public function fetchAll($fetchStyle = \PDO::FETCH_BOTH){
		
		$rows = array();
		
		while($row = $this->fetch($fetchStyle)){
			$rows[] = $row;
		}
		
		return $rows;
	}

	/**
	 * Returns a single column from the next row of a
	 * result set or FALSE if there are no more rows.
	 *
	 * @param integer $columnIndex		 0-indexed number of the column you wish to retrieve from the row. If no 
	 *									 value is supplied, Interface->fetchColumn() 
	 *									 fetches the first column.
	 *
	 * @return string					  returns a single column in the next row of a result set.
	 */
	public function fetchColumn($columnIndex = 0){
		
		$row = $this->fetch(Code::FETCH_NUM);
		
		return $row[(int)$columnIndex];
	}

	/**
	 * Fetches the next row and returns it as an object.
	 *
	 * Fetches the next row and returns it as an object. This function is an alternative to 
	 * Interface->fetch() with Core::FETCH_CLASS or Core::FETCH_OBJ style.
	 *
	 * @param string $className			Name of the created class, defaults to stdClass. 
	 * @param array $args				  Elements of this array are passed to the constructor.
	 *
	 * @return mixed						an instance of the required class with property names that correspond 
	 *									 to the column names or FALSE in case of an error.
	 */
	public function fetchObject($className = 'stdClass', $args = array()){
		
		$object = $this->factory($className, $args);
		
		$assoc = $result->fetchAssoc();
		foreach($assoc as $key => $value){
			$object->$key = $value;
		}
		
		return $object;
	}

	/**
	 * Returns metadata for a column in a result set
	 *
	 * @param integer $column			  The 0-indexed column in the result set.
	 *
	 * @return array						Associative meta data array with the following structure:
	 *
	 *		 native_type				The PHP native type used to represent the column value.
	 *		 driver:decl_type           The SQL type used to represent the column value in the database. 
	 *                                  If the column in the result set is the result of a function, 
	 *                                  this value is not returned by PDOStatement->getColumnMeta().
	 *		 flags					    Any flags set for this column.
	 *		 name						The name of this column as returned by the database.
	 *		 len						The length of this column. Normally -1 for types other than floating point decimals.
	 *		 precision				    The numeric precision of this column. Normally 0 for types other than floating point decimals.
	 *		 pdo_type					The type of this column as represented by the PDO::PARAM_* constants.
	 */
	public function getColumnMeta($column){
		
		$result = $this->getResult();
		$headers = $result->getHeaders();
		
		if(!isset($headers[$column])){
			$columnCount = count($headers[$column]);
			throw new Exception("Invalid column index '{$column}'! Result only has {$columnCount} columns!");
		}
		
		$columnName = $headers[$column];
		
		$metadata = $result->getColumnMetaData($columnName);
		
		$type      = $metadata['datatype'];
		$length    = $metadata['length'];
		$precision = $metadata['precision'];
		
		switch($type){
			case DataType::BOOL:
			case DataType::BOOLEAN:
			case DataType::BIT:
				$phpType = 'bool';
				break;
				
			case DataType::BIGINT:
			case DataType::INT:
			case DataType::INTEGER:
			case DataType::MEDIUMINT:
			case DataType::SMALLINT:
			case DataType::TINYINT:
			case DataType::YEAR:
			case DataType::DEC:
			case DataType::DECIMAL:
				$phpType = 'int';
				break;
				
			case DataType::DOUBLE:
			case DataType::DOUBLE_PRECISION:
			case DataType::FLOAT:
				$phpType = 'float';
				break;
				
			case DataType::CHAR:
			case DataType::VARCHAR:
			case DataType::BINARY:
			case DataType::BLOB:
			case DataType::DATE:
			case DataType::DATETIME:
			case DataType::ENUM:
			case DataType::LONGBLOB:
			case DataType::LONGTEXT:
			case DataType::MEDIUMBLOB:
			case DataType::MEDIUMTEXT:
			case DataType::SET:
			case DataType::TEXT:
			case DataType::TIME:
			case DataType::TIMESTAMP:
			case DataType::TINYTEXT:
			case DataType::VARBINARY:
				$phpType = "string";
				break;
				
			default:
				throw new Error("Invalid data-type!");
		}
		
		
	}

	/**
	 * Advances to the next rowset in a multi-rowset statement handle
	 * 
	 * Some database servers support stored procedures that return more than one rowset 
	 * (also known as a result set). The nextRowset() method enables you to access the second 
	 * and subsequent rowsets associated with a PDOStatement object. Each rowset can have a 
	 * different set of columns from the preceding rowset.
	 *
	 * @return boolean					 Returns TRUE on success or FALSE on failure.
	 */
	public function nextRowset(){
		$this->getResult()->fetch();
	}

	/**
	 * rowCount() returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement 
	 * executed by the corresponding object.
	 *
	 * If the last SQL statement executed by the associated Statement object was a SELECT statement, 
	 * some databases may return the number of rows returned by that statement. However, 
	 * this behaviour is not guaranteed for all databases and should not be 
	 * relied on for portable applications.
	 *
	 * @return integer					 Returns the number of rows.
	 */
	public function rowCount(){
		return $this->getResult()->count();
	}
	
	private $attributes = array();
	
	/**
	 * Retrieve a statement attribute
	 *
	 * @param integer $attribute
	 * @see Core::ATTR_* constants
	 * @return mixed						the attribute value
	 */
	public function getAttribute($attribute){
		if(isset($this->attributes[$attribute])){
			return $this->attributes[$attribute];
		}
	}
	
	/**
	 * Set a statement attribute
	 *
	 * @param integer $attribute
	 * @param mixed $value				 the value of given attribute
	 * @return boolean					 Returns TRUE on success or FALSE on failure.
	 */
	public function setAttribute($attribute, $value){
		switch($attribute){
			
			case Core::ATTR_AUTO_ACCESSOR_OVERRIDE:
			case Core::ATTR_AUTO_FREE_QUERY_OBJECTS:
			case Core::ATTR_AUTOCOMMIT:
			case Core::ATTR_AUTOLOAD_TABLE_CLASSES:
			case Core::ATTR_CACHE:
			case Core::ATTR_CACHE_LIFESPAN:
			case Core::ATTR_CASCADE_SAVES:
			case Core::ATTR_CASE:
			case Core::ATTR_CLIENT_VERSION:
			case Core::ATTR_CMPNAME_FORMAT:
			case Core::ATTR_COLL_KEY:
			case Core::ATTR_COLL_LIMIT:
			case Core::ATTR_COLLECTION_CLASS:
			case Core::ATTR_CONNECTION_STATUS:
			case Core::ATTR_CREATE_TABLES:
			case Core::ATTR_CURSOR:
			case Core::ATTR_CURSOR_NAME:
			case Core::ATTR_DBNAME_FORMAT:
			case Core::ATTR_DECIMAL_PLACES:
			case Core::ATTR_DEF_TABLESPACE:
			case Core::ATTR_DEF_TEXT_LENGTH:
			case Core::ATTR_DEF_VARCHAR_LENGTH:
			case Core::ATTR_DEFAULT_COLUMN_OPTIONS:
			case Core::ATTR_DEFAULT_IDENTIFIER_OPTIONS:
			case Core::ATTR_DEFAULT_PARAM_NAMESPACE:
			case Core::ATTR_DEFAULT_SEQUENCE:
			case Core::ATTR_DEFAULT_TABLE_CHARSET:
			case Core::ATTR_DEFAULT_TABLE_COLLATE:
			case Core::ATTR_DEFAULT_TABLE_TYPE:
			case Core::ATTR_DRIVER_NAME:
			case Core::ATTR_EMULATE_DATABASE:
			case Core::ATTR_ERRMODE:
			case Core::ATTR_EXPORT:
			case Core::ATTR_FETCH_CATALOG_NAMES:
			case Core::ATTR_FETCH_TABLE_NAMES:
			case Core::ATTR_FETCHMODE:
			case Core::ATTR_FIELD_CASE:
			case Core::ATTR_FKNAME_FORMAT:
			case Core::ATTR_HYDRATE_OVERWRITE:
			case Core::ATTR_IDXNAME_FORMAT:
			case Core::ATTR_LISTENER:
			case Core::ATTR_LOAD_REFERENCES:
			case Core::ATTR_MAX_COLUMN_LEN:
			case Core::ATTR_MAX_IDENTIFIER_LENGTH:
			case Core::ATTR_MODEL_CLASS_PREFIX:
			case Core::ATTR_MODEL_LOADING:
			case Core::ATTR_NAME_PREFIX:
			case Core::ATTR_ORACLE_NULLS:
			case Core::ATTR_PERSISTENT:
			case Core::ATTR_PORTABILITY:
			case Core::ATTR_PREFETCH:
			case Core::ATTR_QUERY_CACHE:
			case Core::ATTR_QUERY_CACHE_LIFESPAN:
			case Core::ATTR_QUERY_CLASS:
			case Core::ATTR_QUERY_LIMIT:
			case Core::ATTR_QUOTE_IDENTIFIER:
			case Core::ATTR_RECORD_LISTENER:
			case Core::ATTR_RECURSIVE_MERGE_FIXTURES:
			case Core::ATTR_RESULT_CACHE:
			case Core::ATTR_RESULT_CACHE_LIFESPAN:
			case Core::ATTR_SEQCOL_NAME:
			case Core::ATTR_SEQNAME_FORMAT:
			case Core::ATTR_SERVER_INFO:
			case Core::ATTR_SERVER_VERSION:
			case Core::ATTR_STATEMENT_CLASS:
			case Core::ATTR_STRINGIFY_FETCHES:
			case Core::ATTR_TABLE_CLASS:
			case Core::ATTR_TABLE_CLASS_FORMAT:
			case Core::ATTR_TBLCLASS_FORMAT:
			case Core::ATTR_TBLNAME_FORMAT:
			case Core::ATTR_THROW_EXCEPTIONS:
			case Core::ATTR_TIMEOUT:
			case Core::ATTR_USE_DQL_CALLBACKS:
			case Core::ATTR_USE_NATIVE_ENUM:
			case Core::ATTR_USE_NATIVE_SET:
			case Core::ATTR_VALIDATE:
				$this->attributes[$attribute] = $value;
				break;
			
			default:
				throw new Exception("Invalid attribute '{$attribute}'! Must be one of Core::ATTR_* constants.");
		}
	}

	/**
	 * Set the default fetch mode for this statement 
	 *
	 * @param integer $mode				The fetch mode must be one of the Core::FETCH_* constants.
	 * @return boolean					 Returns 1 on success or FALSE on failure.
	 */
	public function setFetchMode($mode, $arg1 = null, $arg2 = null){
		
	}
}