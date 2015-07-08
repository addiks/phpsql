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

namespace Addiks\PHPSQL\Executor;

use Addiks\PHPSQL\Table;
use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Value\Enum\Page\Index\ForeignKeyMethod;
use Addiks\PHPSQL\Entity\Page\Schema\Index;
use Addiks\PHPSQL\Value\Text\Annotation;
use Addiks\PHPSQL\Entity\Page\Column;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Value\Enum\Page\Schema\InsertMethod;
use Addiks\PHPSQL\Value\Enum\Page\Schema\RowFormat;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;
use Addiks\PHPSQL\Value\Enum\Page\Index\Engine as IndexEngine;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Type;
use Addiks\PHPSQL\Value\Enum\Page\Index\Type as IndexType;
use Addiks\PHPSQL\Entity\Schema;
use Addiks\PHPSQL\Entity\Page\Schema as SchemaPage;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\Entity\Result\Temporary;
use Addiks\PHPSQL\Database;
use Addiks\PHPSQL\Index as IndexResource;
use ErrorException;

class CreateTableExecutor extends Executor
{
    
    public function __construct(SchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }
    
    protected function executeConcreteJob($statement, array $parameters = array())
    {
        /* @var $statement Table */
        
        /* @var $databaseSchema Schema */
        $databaseSchema = $this->schemaManager->getSchema();
        
        /* @var $schemaPage SchemaPage */
        $this->factorize($schemaPage);
        
        $schemaPage->setName($statement->getName());
        $schemaPage->setType(Type::TABLE());
        $schemaPage->setEngine(Engine::factory($statement->getEngine()->getName()));
        $schemaPage->setCollation(strtoupper($statement->getCollate()));
        $schemaPage->setUseChecksum($statement->getUseChecksum());
        $schemaPage->setMaxRows($statement->getMaximumRows());
        $schemaPage->setMinRows($statement->getMinimumRows());
        $schemaPage->setPackKeys(($statement->getPackKeys()));
        $schemaPage->setDelayKeyWrite(($statement->getDelayKeyWrite()));
        $schemaPage->setRowFormat(RowFormat::factory($statement->getRowFormat()));
        $schemaPage->setInsertMethod(InsertMethod::factory($statement->getInsertMethod()));
        
        $databaseSchema->registerTableSchema($schemaPage);
        
        /* @var $tableSchema TableSchema */
        $tableSchema = $this->schemaManager->getTableSchema($statement->getName());
        
        ### WRITE COLUMNS
        
        switch(true){
        
            case (is_array($statement->getColumnDefinition())):
                foreach ($statement->getColumnDefinition() as $name => $column) {
                    /* @var $column ColumnDefinition */
                    
                    /* @var $columnPage Column */
                    $this->factorize($columnPage);
                    
                    $columnPage->setName($name);
                    $columnPage->setDataType(DataType::factory($column->getDataType()->getName()));
                    
                    /* @var $dataType DataType */
                    $dataType = $columnPage->getDataType();
                    
                    $flags = 0;
                    
                    if ($column->getIsPrimaryKey()) {
                        $flags = $flags ^ Column::EXTRA_PRIMARY_KEY;
                    }
                    if ($column->getIsUnique()) {
                        $flags = $flags ^ Column::EXTRA_UNIQUE_KEY;
                    }
                    if (!$column->getIsNullable()) {
                        $flags = $flags ^ Column::EXTRA_NOT_NULL;
                    }
                    if ($column->getIsAutoIncrement()) {
                        $flags = $flags ^ Column::EXTRA_AUTO_INCREMENT;
                    }
                    
                    $columnPage->setExtraFlags($flags);
                    
                    $dataType = $column->getDataType();

                    $columnPage->setLength($dataType->getByteLength());
                    $columnPage->setSecondLength($dataType->getSecondByteLength());

                    if (!is_null($column->getDataTypeLength())) {
                        $columnPage->setLength($column->getDataTypeLength());
                    }
                    
                    if (!is_null($column->getDataTypeSecondLength())) {
                        $columnPage->setSecondLength($column->getDataTypeSecondLength());
                    }
                    
                    $tableSchema->writeColumn(null, $columnPage);
                }
                break;
                    
            case ($statement->getColumnDefinition() instanceof Select):
                break;
                
            case ($statement->getColumnDefinition() instanceof Table):
                break;
        }
        
        foreach ($statement->getIndexes() as $indexName => $index) {
            /* @var $index Index */
            
            /* @var $indexSchemaPage Index */
            $this->factorize($indexSchemaPage);
            
            $indexSchemaPage->setName($indexName);
            $indexSchemaPage->setEngine(IndexEngine::BTREE());
            
            switch(true){
                case $index->getIsPrimary():
                    $indexSchemaPage->setType(IndexType::PRIMARY());
                    break;
                    
                case $index->getIsUnique():
                    $indexSchemaPage->setType(IndexType::UNIQUE());
                    break;
                    
                default:
                    $indexSchemaPage->setType(IndexType::INDEX());
                    break;
            }
            
            $method = ForeignKeyMethod::factory($index->getForeignKeyOnDeleteReferenceOption()->getName());
            $indexSchemaPage->setForeignKeyOnDeleteMethod($method);
            
            $method = ForeignKeyMethod::factory($index->getForeignKeyOnUpdateReferenceOption()->getName());
            $indexSchemaPage->setForeignKeyOnUpdateMethod($method);
            
            /* @var $indexStorage Storage */
            $indexStorage = $this->getIndexStorage($index->getName(), $statement->getName());
            
            $keyLength = 0;
            $columns = array();
            foreach ($index->getColumns() as $indexColumnName) {
                $indexColumnId = $tableSchema->getColumnIndex((string)$indexColumnName);
                if (strlen($indexColumnId)<=0) {
                    throw new ErrorException("Could not find index column '{$indexColumnName}' in table-schema!");
                }
                $columns[] = $indexColumnId;
                $keyLength += $statement->getColumnDefinition()[(string)$indexColumnName]->getDataSize();
            }
            
            $indexSchemaPage->setColumns($columns);
            $indexSchemaPage->setKeyLength($keyLength);
            
            $indexPosition = $tableSchema->addIndexPage($indexSchemaPage);
            
            /* @var $indexResource IndexResource */
            $this->factorize($indexResource, [$indexPosition, $statement->getName()]);
            
            $indexResource->getIndexBackend();
        }
    
        /* @var $tableResource Table */
        $this->factorize($tableResource, [$tableSchema]);
        
        ### RESULT
        
        /* @var $result Temporary */
        $this->factorize($result);
        
        $result->setIsSuccess(true);
        
        return $result;
    }
}
