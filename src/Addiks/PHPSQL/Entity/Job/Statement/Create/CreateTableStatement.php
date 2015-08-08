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

namespace Addiks\PHPSQL\Entity\Job\Statement\Create;

use Addiks\PHPSQL\Entity\Job\Statement\CreateStatement;
use Addiks\PHPSQL\Entity\Job\Part\Join\Table as TablePart;
use Addiks\PHPSQL\Value\Enum\Sql\TableOptions\InsertMethod;
use Addiks\PHPSQL\Value\Enum\Sql\TableOptions\RowFormat;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;
use Addiks\PHPSQL\Entity\Job\Part\Index;
use Addiks\PHPSQL\Entity\Job\Statement\Select;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Entity\Job\Statement\Create;
use Addiks\PHPSQL\StatementExecutor\CreateTableExecutor;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;

/**
 *
 */
class CreateTableStatement extends CreateStatement
{
    
    const EXECUTOR_CLASS = CreateTableExecutor::class;

    /**
     * Temporary tables exist only within one runtime and are dropped when the runtime is finished.
     * This is useful when you want to make sure there are no conflicting table-names.
     *
     * Temporary tables will hide non-temporary tables with the same name.
     *
     * @var bool
     */
    private $isTemporaryTable = false;
    
    public function setIsTemporaryTable($bool)
    {
        $this->isTemporaryTable = (bool)$bool;
    }
    
    public function getIsTemporaryTable()
    {
        return $this->isTemporaryTable;
    }
    
    private $columnDefinition = array();
    
    public function getColumnDefinition()
    {
        return $this->columnDefinition;
    }
    
    public function setFromSelectStatement(Select $select)
    {
        $this->columnDefinition = $select;
    }
    
    public function setLikeTable(Table $table)
    {
        $this->columnDefinition = $table;
    }
    
    public function addColumnDefinition(ColumnDefinition $column)
    {
        if (!is_array($this->columnDefinition)) {
            $this->columnDefinition = array();
        }
        if (isset($this->columnDefinition[$column->getName()])) {
            throw new MalformedSql("Column '{$column->getName()}' already defined!");
        }
        $this->columnDefinition[$column->getName()] = $column;
        
        if ($column->getIsPrimaryKey()) {
            $index = new Index();
            $index->setIsPrimary(true);
            $index->setName("PRIMARY");
            $index->addColumn(ColumnSpecifier::factory($column->getName()));
            $this->addIndex($index);
            
        } elseif ($column->getIsUnique()) {
            $index = new Index();
            $index->setIsUnique(true);
            $index->setName($column->getName());
            $index->addColumn(ColumnSpecifier::factory($column->getName()));
            $this->addIndex($index);
        }
    }
    
    private $indexes = array();
    
    public function addIndex(Index $index)
    {
        if (isset($this->indexes[$index->getName()])) {
            throw new MalformedSql("Index '{$index->getName()}' already defined!");
        }
        $this->indexes[$index->getName()] = $index;
        
        if (is_array($this->columnDefinition)) {
            if ($index->getIsPrimary()) {
                foreach ($index->getColumns() as $column) {
                    /* @var $column ColumnSpecifier */
                    
                    if (!isset($this->columnDefinition[$column->getColumn()])) {
                        throw new Conflict("Cannot set undefined column '{$column}' as primary key!");
                    }
                    
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $this->columnDefinition[$column->getColumn()];
                    
                    $columnDefinition->setIsPrimaryKey(true);
                }
            }
        }
    }
    
    public function getIndexes()
    {
        return $this->indexes;
    }
    
    private $checks = array();
    
    public function addCheck(Condition $condition)
    {
        $this->checks[] = $condition;
    }
    
    ### TABLE-OPTIONS
    
    /**
     * Normally you would tell mysql with this what engine you want to use,
     * but the internal database has no different 'engines', so this is not used.
     * This is stored anyway to enhance compatibility.
     * @var string
     */
    private $engine;
    
    public function setEngine(Engine $engine)
    {
        $this->engine = $engine;
    }
    
    public function getEngine()
    {
        if (is_null($this->engine)) {
            $this->engine = Engine::MYISAM();
        }
        return $this->engine;
    }
    
    /**
     * The current (start) value of the AUTO_INCREMENT counter.
     * @var int
     */
    private $autoIncrement;
    
    public function setAutoIncrement($integer)
    {
        $this->autoIncrement = (int)$integer;
    }
    
    public function getAutoIncrement()
    {
        return $this->autoIncrement;
    }
    
    /**
     * This is used in MySQL by the MyISAM engine to determine how big the table can get before its 'full'.
     * Such a limitation does not exist in the internal database, but nonetheless this attribute exists,
     * so it will be stored to enhance compatibility.
     * @var int
     */
    private $avarageRowLength;
    
    public function setAverageRowLength($length)
    {
        $this->avarageRowLength = (int)$length;
    }
    
    public function getAverageRowLength()
    {
        return $this->avarageRowLength;
    }
    
    private $characterSet;
    
    public function setCharacterSet($characterSet)
    {
        $this->characterSet = (string)$characterSet;
    }
    
    public function getCharacterSet()
    {
        return $this->characterSet;
    }
    
    private $collate;
    
    public function setCollate($collate)
    {
        $this->collate = (string)$collate;
    }
    
    public function getCollate()
    {
        return $this->collate;
    }
    
    private $useChecksum = true;
    
    public function setUseChecksum($bool)
    {
        $this->useChecksum = (bool)$bool;
    }
    
    public function getUseChecksum()
    {
        return $this->useChecksum;
    }
    
    private $comment;
    
    public function setComment($comment)
    {
        $this->comment = (string)$comment;
    }
    
    public function getComment()
    {
        return $this->comment;
    }
    
    private $connectString;
    
    public function setConnectString($connectionString)
    {
        $this->connectString = $connectionString;
    }
    
    public function getConnectString()
    {
        return $this->connectString;
    }
    
    private $maximumRows;
    
    public function setMaximumRows($int)
    {
        $int = (int)$int;
        if ($int < 0) {
            throw new MalformedSql("Maximum-rows '{$int}' cannot be negative!");
        }
        if ($int < $this->getMinimumRows()) {
            $this->setMinimumRows($int);
        }
        $this->maximumRows = $int;
    }
    
    public function getMaximumRows()
    {
        return $this->maximumRows;
    }
    
    private $minimumRows = 0;
    
    public function setMinimumRows($int)
    {
        $int = (int)$int;
        if ($int < 0) {
            throw new MalformedSql("Minimum-rows '{$int}' cannot be negative!");
        }
        if (!is_null($this->getMaximumRows()) && $int > $this->getMaximumRows()) {
            $this->setMaximumRows($int);
        }
        $this->minimumRows = $int;
    }
    
    public function getMinimumRows()
    {
        return $this->minimumRows;
    }
    
    private $packKeys = false;
    
    public function setPackKeys($bool)
    {
        $this->packKeys = (bool)$bool;
    }
    
    public function getPackKeys()
    {
        return $this->packKeys;
    }
    
    /**
     * Not used.
     * In MyISAM this is used to encrypt data-files.
     * In this internal database this makes no sense,
     * because the table-data would be stored with the password together in the schema.
     * @var string
     */
    private $password;
    
    public function setPassword($password)
    {
        $this->password = $password;
    }
    
    public function getPassword()
    {
        return $this->password;
    }
    
    private $delayKeyWrite = false;
    
    public function setDelayKeyWrite($bool)
    {
        $this->delayKeyWrite = (bool)$bool;
    }
    
    public function getDelayKeyWrite()
    {
        return $this->delayKeyWrite;
    }
    
    private $rowFormat;
    
    public function setRowFormat(RowFormat $format)
    {
        $this->rowFormat = $format;
    }
    
    public function getRowFormat()
    {
        if (is_null($this->rowFormat)) {
            $this->rowFormat = RowFormat::DYNAMIC();
        }
        return $this->rowFormat;
    }
    
    private $union = array();
    
    public function addUnionTable(TablePart $table)
    {
        $this->union[] = $table;
    }
    
    public function getUnionTables()
    {
        return $this->union;
    }
    
    private $insertMethod;
    
    public function setInsertMethod(InsertMethod $method)
    {
        $this->insertMethod = $method;
    }
    
    public function getInsertMethod()
    {
        if (is_null($this->insertMethod)) {
            $this->insertMethod = InsertMethod::LAST();
        }
        return $this->insertMethod;
    }
    
    /**
     * This value is not used because everything is stored in the file system.
     * The path will be stored anyway just to get it later.
     * @var string
     */
    private $dataDirectory;
    
    public function setDataDirectory($directory)
    {
        $this->dataDirectory = $directory;
    }
    
    public function getDataDirectory()
    {
        return $this->dataDirectory;
    }
    
    /**
     * This value is not used because everything is stored in the file system.
     * The path will be stored anyway just to get it later.
     * @var string
     */
    private $indexDirectory;
    
    public function setIndexDirectory($directory)
    {
        $this->indexDirectory = $directory;
    }
    
    public function getIndexDirectory()
    {
        return $this->indexDirectory;
    }
    
    public function checkPlausibility()
    {
        parent::checkPlausibility();
        
        if (is_array($this->getColumnDefinition())) {
            $hasPrimaryKey = false;
            foreach ($this->getColumnDefinition() as $column) {
                if ($column->getIsPrimaryKey()) {
                    $hasPrimaryKey = true;
                    break;
                }
            }
            if (!$hasPrimaryKey) {
                reset($this->columnDefinition);
                $columnName = key($this->columnDefinition);
                $this->columnDefinition[$columnName]->setIsPrimaryKey(true);
                $index = new Index();
                $index->setIsPrimary(true);
                $index->setName("PRIMARY");
                $index->addColumn(ColumnSpecifier::factory($columnName));
                $this->addIndex($index);
            }
        }
        
    }
}
