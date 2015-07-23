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

namespace Addiks\PHPSQL\StatementExecutor;

use ErrorException;
use Addiks\PHPSQL\Table;
use Addiks\PHPSQL\Value\Enum\Page\Index\ForeignKeyMethod;
use Addiks\PHPSQL\Entity\Page\Schema\Index;
use Addiks\PHPSQL\Value\Text\Annotation;
use Addiks\PHPSQL\Entity\Page\ColumnPage;
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
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Entity\Job\Statement\Create\CreateTableStatement;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\TableManager;
use Addiks\PHPSQL\Entity\Page\ColumnPagePage;
use Addiks\PHPSQL\Entity\Page\Schema\IndexPage;
use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;

class CreateTableExecutor implements StatementExecutorInterface
{
    
    public function __construct(
        SchemaManager $schemaManager,
        TableManager $tableManager
    ) {
        $this->schemaManager = $schemaManager;
        $this->tableManager = $tableManager;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    protected $tableManager;

    public function getTableManager()
    {
        return $this->tableManager;
    }
    
    public function canExecuteJob(StatementJob $statement)
    {
        return $statement instanceof CreateTableStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement CreateTableStatement */
        
        /* @var $databaseSchema Schema */
        $databaseSchema = $this->schemaManager->getSchema();

        $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        
        $schemaPage = new SchemaPage();
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
                    
                    $columnPage = new ColumnPage();
                    $columnPage->setName($name);
                    $columnPage->setDataType(DataType::factory($column->getDataType()->getName()));
                    
                    /* @var $dataType DataType */
                    $dataType = $columnPage->getDataType();
                    
                    $flags = 0;
                    
                    if ($column->getIsPrimaryKey()) {
                        $flags = $flags ^ ColumnPage::EXTRA_PRIMARY_KEY;
                    }
                    if ($column->getIsUnique()) {
                        $flags = $flags ^ ColumnPage::EXTRA_UNIQUE_KEY;
                    }
                    if (!$column->getIsNullable()) {
                        $flags = $flags ^ ColumnPage::EXTRA_NOT_NULL;
                    }
                    if ($column->getIsAutoIncrement()) {
                        $flags = $flags ^ ColumnPage::EXTRA_AUTO_INCREMENT;
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

                    if (!is_null($column->getDefaultValue())
                    && !$columnPage->isDefaultValueInFile()) {
                        $columnPage->setDefaultValue($column->getDefaultValue());
                    }
                    
                    $columnIndex = $tableSchema->writeColumn(null, $columnPage);

                    if (!is_null($column->getDefaultValue())
                    && $columnPage->isDefaultValueInFile()) {
                        $defaultValueFilepath = sprintf(
                            FilePathes::FILEPATH_DEFAULT_VALUE,
                            $schemaId,
                            $statement->getName(),
                            $columnIndex
                        );
                        $this->tableManager->getFilesystem()->putFileContents(
                            $defaultValueFilepath,
                            $column->getDefaultValue()
                        );
                    }
                    
                }
                break;
                    
            case ($statement->getColumnDefinition() instanceof Select):
                break;
                
            case ($statement->getColumnDefinition() instanceof Table):
                break;
        }
        
        foreach ($statement->getIndexes() as $indexName => $index) {
            /* @var $index Index */
            
            $indexSchemaPage = new IndexPage();
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

            $tableSchema->addIndexPage($indexSchemaPage);
            
        }
    
        ### RESULT
        
        $result = new TemporaryResult();
        $result->setIsSuccess(true);
        
        return $result;
    }
}
