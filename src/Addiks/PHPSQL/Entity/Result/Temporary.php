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

namespace Addiks\PHPSQL\Entity\Result;

use Addiks\PHPSQL\Entity;

/**
 * This resultset will not be stored permanent but only to the RAM.
 * It can be used for small results like CREATE DATABASE, ALTER TABLE or SHOW DATABASES
 *
 * @author gerrit
 *
 */
class Temporary extends Entity implements ResultInterface, \IteratorAggregate
{

    public function __construct(array $columnNames = array())
    {
        $this->columnNames = $columnNames;
    }

    private $isSuccess = true;

    public function setIsSuccess($bool)
    {
        $this->isSuccess = (bool)$bool;
    }

    public function getIsSuccess()
    {
        return $this->isSuccess;
    }

    public function getHeaders()
    {
        return $this->getColumnNames();
    }

    /**
     * @return bool
     */
    function getHasResultRows()
    {
        return count($this->rows)>0;
    }
    
    private $lastInsertId = array();
    
    /**
     * @return array
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }
    
    public function setLastInsertId(array $row)
    {
        $this->lastInsertId = $row;
    }
    
    public function seek($rowId)
    {

        /* @var $iterator \ArrayIterator */
        $iterator = $this->getIterator();

        $iterator->seek($rowId);
    }

    private $columnNames;

    public function getColumnNames()
    {
        return $this->columnNames;
    }

    private $rows = array();

    public function getRows()
    {
        return $this->rows;
    }

    private $iterator;

    public function getIterator()
    {
        if (is_null($this->iterator)) {
            $this->iterator = new \ArrayIterator($this->getRows());
        }
        return $this->iterator;
    }

    public function count()
    {
        return count($this->rows);
    }
    
    public function addRow(array $row)
    {

        if (count($row) !== count($this->getColumnNames())) {
            throw new \ErrorException("Tried to insert row into temporary-result which does not comply with result-schema!");
        }

        $newRow = array();

        foreach ($row as $index => $value) {
            if (is_int($index)) {
                $index = $this->getColumnNames()[$index];
            }
            $newRow[$index] = $value;
        }

        $this->rows[] = $newRow;
    }
    
    /**
     * Alias of fetchArray
     * @return array
     */
    public function fetch()
    {
        return $this->fetchArray();
    }
    
    /**
     * @return array
     */
    public function fetchArray()
    {
    
        $iterator = $this->getIterator();
        
        $row = $iterator->current();
        $iterator->next();
    
        if (!is_array($row)) {
            return $row;
        }
        
        $number = 0;
        foreach ($row as $value) {
            $row[$number] = $value;
            $number++;
        }
    
        return $row;
    }
    
    /**
     * @return array
     */
    public function fetchAssoc()
    {
        
        $iterator = $this->getIterator();
        
        $row = $iterator->current();
        $iterator->next();
        
        return $row;
    }
    
    /**
     * @return array
     */
    public function fetchRow()
    {

        $iterator = $this->getIterator();
        
        $row = $iterator->current();
        $iterator->next();
        
        if (!is_array($row)) {
            return $row;
        }
        
        $returnRow = array();
        $number = 0;
        foreach ($row as $value) {
            $returnRow[$number] = $value;
            $number++;
        }
    
        return $returnRow;
    }
    
    private $columnMetaData = array();
    
    public function setColumnMetaData($columnName, array $data)
    {
        $this->columnMetaData[$columnName] = $data;
    }
    
    public function getColumnMetaData($columnName)
    {
        return $this->columnMetaData[$columnName];
    }
}
