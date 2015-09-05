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
use Addiks\PHPSQL\TableInterface;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;

/**
 * This resultset will not be stored permanent but only to the RAM.
 * It can be used for small results like CREATE DATABASE, ALTER TABLE or SHOW DATABASES
 *
 * @author gerrit
 *
 */
class TemporaryResult extends Entity implements ResultInterface
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

    public function addRow(array $row)
    {

        if (count($this->getColumnNames()) > 0 && count($row) !== count($this->getColumnNames())) {
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
        $this->iterator = null;
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
        $row = $this->current();
        $this->next();
    
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
        $row = $this->current();
        $this->next();
        
        return $row;
    }
    
    /**
     * @return array
     */
    public function fetchRow()
    {
        $row = $this->current();
        $this->next();
        
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

    public function getDBSchemaId()
    {
    }
    
    public function getDBSchema()
    {
    }
    
    public function getTableName()
    {
    }
    
    public function getTableId()
    {
    }

    protected $tableSchema;
    
    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        if (is_null($this->tableSchema)) {
            $columnFile = new FileResourceProxy(fopen("php://memory", "w"));
            $indexFile  = new FileResourceProxy(fopen("php://memory", "w"));
            $this->tableSchema = new TableSchema($columnFile, $indexFile);

            foreach ($this->columnNames as $columnName) {
                $columnPage = new ColumnPage();
                $columnPage->setName($columnName);
                $columnPage->setDataType(DataType::VARCHAR());
                $columnPage->setLength(1024);
                $this->tableSchema->addColumnPage($columnPage);
            }
        }
        return $this->tableSchema;
    }
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition)
    {
    }
    
    public function getCellData($rowId, $columnId)
    {
    }
    
    public function setCellData($rowId, $columnId, $data)
    {
    }
    
    public function getRowData($rowId = null)
    {
    }
    
    public function setRowData($rowId, array $rowData)
    {
    }
    
    public function addRowData(array $rowData)
    {
    }
    
    public function removeRow($rowId)
    {
    }
    
    public function doesRowExists($rowId = null)
    {
        return $this->count() >= $rowId;
    }

    ### ITERATOR

    protected $index = 0;

    public function rewind()
    {
        $this->index = 0;
    }
    
    public function valid()
    {
        return $this->index < count($this->rows);
    }

    public function current()
    {
        if (isset($this->rows[$this->index])) {
            return $this->rows[$this->index];
        }
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        $this->index++;
    }
    
    public function tell()
    {
        return $this->index;
    }
    
    public function count()
    {
        return count($this->rows);
    }
    
    public function seek($rowId)
    {
        $this->index = (int)$rowId;
    }

}
