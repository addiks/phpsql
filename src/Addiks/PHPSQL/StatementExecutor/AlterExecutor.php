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

use Addiks\PHPSQL\Value\Enum\Sql\Alter\DataChange\AlterAttributeType;
use Addiks\PHPSQL\Result\Temporary;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\ValueResolver\ValueResolver;
use Addiks\PHPSQL\Table\TableManager;
use Addiks\PHPSQL\Result\TemporaryResult;
use Addiks\PHPSQL\Job\Statement\AlterStatement;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Job\Part\ColumnDefinition;
use Addiks\PHPSQL\Entity\Page\SchemaPage as SchemaPage;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\DataConverter;
use Addiks\PHPSQL\Column\ColumnDataFactoryInterface;
use Addiks\PHPSQL\Column\ColumnDataInterface;
use Addiks\PHPSQL\Table\TableSchema;

class AlterExecutor implements StatementExecutorInterface
{

    public function __construct(
        SchemaManager $schemaManager,
        TableManager $tableManager,
        ValueResolver $valueResolver,
        DataConverter $dataConverter = null
    ) {
        if (is_null($dataConverter)) {
            $dataConverter = new DataConverter();
        }

        $this->schemaManager = $schemaManager;
        $this->valueResolver = $valueResolver;
        $this->tableManager = $tableManager;
        $this->dataConverter = $dataConverter;
    }

    private $dataConverter;

    public function getDataConverter()
    {
        return $this->dataConverter;
    }

    private $valueResolver;

    public function getValueResolver()
    {
        return $this->valueResolver;
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
        return $statement instanceof AlterStatement;
    }

    public function executeJob(StatementJob $statement, array $parameters = array())
    {
        /* @var $statement AlterStatement */

        $executionContext = new ExecutionContext(
            $this->schemaManager,
            $statement,
            $parameters
        );

        /* @var $tableSpecifier TableSpecifier */
        $tableSpecifier = $statement->getTable();

        /* @var $table TableInterface */
        $table = $this->tableManager->getTable(
            $tableSpecifier->getTable(),
            $tableSpecifier->getDatabase()
        );

        $tableId = $this->tableManager->getTableIdByName(
            $tableSpecifier->getTable(),
            $tableSpecifier->getDatabase()
        );

        /* @var $tableSchema TableSchema */
        $tableSchema = $table->getTableSchema();

        foreach ($statement->getDataChanges() as $dataChange) {
            /* @var $dataChange DataChange */

            switch($dataChange->getAttribute()){

                case AlterAttributeType::ADD():
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $dataChange->getSubject();

                    /* @var $columnSchema ColumnSchema */
                    $columnSchema = $this->convertColumnDefinitionToColumnSchema(
                        $columnDefinition,
                        $executionContext
                    );

                    $columnId = $tableSchema->addColumnSchema($columnSchema);

                    /* @var $columnDataFactory ColumnDataFactoryInterface */
                    $columnDataFactory = $this->tableManager->getColumnDataFactory(
                        $tableSpecifier->getTable(),
                        $tableSpecifier->getDatabase()
                    );

                    /* @var $columnData ColumnDataInterface */
                    $columnData = $columnDataFactory->createColumnData(
                        $tableSpecifier->getDatabase(),
                        $tableId,
                        $columnId,
                        $columnSchema
                    );

                    $table->addColumn($columnSchema, $columnData);
                    break;

                case AlterAttributeType::DROP():
                    /* @var $columnSpecifier ColumnSpecifier */
                    $columnSpecifier = $dataChange->getSubject();

                    $columnId = $tableSchema->getColumnIndex($columnSpecifier->getColumn());

                    $tableSchema->removeColumn($columnId);
                    break;

                case AlterAttributeType::SET_AFTER():
                case AlterAttributeType::SET_FIRST():
                case AlterAttributeType::MODIFY():
                    /* @var $columnDefinition ColumnDefinition */
                    $columnDefinition = $dataChange->getSubject();

                    /* @var $columnSchema ColumnSchema */
                    $columnSchema = $this->convertColumnDefinitionToColumnSchema(
                        $columnDefinition,
                        $executionContext
                    );

                    $columnId = $tableSchema->getColumnIndex($columnSchema->getName());

                    /* @var $oldColumnSchema ColumnSchema */
                    $oldColumnSchema = $tableSchema->getColumn($columnId);

                    $columnSchema->setIndex($oldColumnSchema->getIndex());

                    $tableSchema->writeColumn($columnId, $columnSchema);

                    /* @var $columnDataFactory ColumnDataFactoryInterface */
                    $columnDataFactory = $this->tableManager->getColumnDataFactory(
                        $tableSpecifier->getTable(),
                        $tableSpecifier->getDatabase()
                    );

                    /* @var $columnData ColumnDataInterface */
                    $columnData = $columnDataFactory->createColumnData(
                        $tableSpecifier->getDatabase(),
                        $tableId,
                        $columnId,
                        $columnSchema
                    );

                    $table->modifyColumn($columnSchema, $columnData);

                    if ($dataChange->getAttribute() === AlterAttributeType::SET_FIRST()) {
                        $subjectColumnIndex = $tableSchema->getColumnIndex($columnDefinition->getName());
                        $subjectColumnSchema = $tableSchema->getColumn($subjectColumnIndex);
                        $oldIndex = $subjectColumnSchema->getIndex();
                        foreach ($tableSchema->getColumnIterator() as $columnIndex => $columnPage) {
                            if ($columnPage->getIndex() < $oldIndex) {
                                $columnPage->setIndex($columnPage->getIndex()+1);
                                $tableSchema->writeColumn($columnIndex, $columnPage);
                            }
                        }
                        $subjectColumnSchema->setIndex(0);
                        $tableSchema->writeColumn($subjectColumnIndex, $subjectColumnSchema);

                    } elseif($dataChange->getAttribute() === AlterAttributeType::SET_AFTER()) {
                        /* @var $afterColumn ColumnSpecifier */
                        $afterColumn = $dataChange->getValue();

                        $afterColumnIndex = $tableSchema->getColumnIndex($afterColumn->getColumn());
                        $afterColumnSchema = $tableSchema->getColumn($afterColumnIndex);
                        $subjectColumnIndex = $tableSchema->getColumnIndex($columnDefinition->getName());
                        $subjectColumnSchema = $tableSchema->getColumn($subjectColumnIndex);

                        if ($afterColumnSchema->getIndex() < $subjectColumnSchema->getIndex()) {
                            foreach ($tableSchema->getColumnIterator() as $columnIndex => $columnPage) {
                                if ($columnPage->getIndex() > $afterColumnSchema->getIndex()
                                &&  $columnPage->getIndex() < $subjectColumnSchema->getIndex()) {
                                    $columnPage->setIndex($columnPage->getIndex()+1);
                                    $tableSchema->writeColumn($columnIndex, $columnPage);
                                }
                            }

                            $subjectColumnSchema->setIndex($afterColumnSchema->getIndex() + 1);
                            $tableSchema->writeColumn($subjectColumnIndex, $subjectColumnSchema);

                        } else {
                            foreach ($tableSchema->getColumnIterator() as $columnIndex => $columnPage) {
                                if ($columnPage->getIndex() > $afterColumnSchema->getIndex()
                                &&  $columnPage->getIndex() < $subjectColumnSchema->getIndex()) {
                                    $columnPage->setIndex($columnPage->getIndex()-1);
                                    $tableSchema->writeColumn($columnIndex, $columnPage);
                                }
                            }
                            $subjectColumnSchema->setIndex($afterColumnSchema->getIndex());
                            $tableSchema->writeColumn($subjectColumnIndex, $subjectColumnSchema);
                            $afterColumnSchema->setIndex($afterColumnSchema->getIndex() - 1);
                            $tableSchema->writeColumn($afterColumnSchema, $afterColumnSchema);
                        }
                    }
                    break;

                case AlterAttributeType::RENAME():
                    $databaseSchema = $this->schemaManager->getSchema($tableSpecifier->getDatabase());
                    /* @var $tablePage SchemaPage */
                    $tableIndex = $databaseSchema->getTableIndex($tableSpecifier->getTable());
                    $tablePage = $databaseSchema->getTablePage($tableIndex);
                    $tablePage->setName($dataChange->getValue());
                    $databaseSchema->registerTableSchema($tablePage, $tableIndex);
                    break;
 
                case AlterAttributeType::CHARACTER_SET():
                    break;

                case AlterAttributeType::COLLATE():
                    break;

                case AlterAttributeType::CONVERT():
                    break;

                case AlterAttributeType::DEFAULT_VALUE():
                    break;

                case AlterAttributeType::ORDER_BY_ASC():
                    break;

                case AlterAttributeType::ORDER_BY_DESC():
                    break;

            }
        }

        $result = new TemporaryResult();

        return $result;
    }

    protected function convertColumnDefinitionToColumnSchema (
        ColumnDefinition $columnDefinition,
        ExecutionContext $executionContext
    ) {

        $columnPage = new ColumnSchema();
        $columnPage->setName($columnDefinition->getName());

        /* @var $dataType DataType */
        $dataType = $columnDefinition->getDataType();

        $columnPage->setDataType($dataType);

        if (!is_null($columnDefinition->getDataTypeLength())) {
            $columnPage->setLength($columnDefinition->getDataTypeLength());
        }

        if (!is_null($columnDefinition->getDataTypeSecondLength())) {
            $columnPage->setSecondLength($columnDefinition->getDataTypeSecondLength());
        }

        $flags = 0;

        if ($columnDefinition->getIsAutoIncrement()) {
            $flags = $flags ^ ColumnSchema::EXTRA_AUTO_INCREMENT;
        }

        if (!$columnDefinition->getIsNullable()) {
            $flags = $flags ^ ColumnSchema::EXTRA_NOT_NULL;
        }

        if ($columnDefinition->getIsPrimaryKey()) {
            $flags = $flags ^ ColumnSchema::EXTRA_PRIMARY_KEY;
        }

        if ($columnDefinition->getIsUnique()) {
            $flags = $flags ^ ColumnSchema::EXTRA_UNIQUE_KEY;
        }

        if ($columnDefinition->getIsUnsigned()) {
            $flags = $flags ^ ColumnSchema::EXTRA_UNSIGNED;
        }

        if (false) {
            $flags = $flags ^ ColumnSchema::EXTRA_ZEROFILL;
        }

        $columnPage->setExtraFlags($flags);

        #$columnPage->setFKColumnIndex($index);
        #$columnPage->setFKTableIndex($index);

        /* @var $defaultValue Value */
        $defaultValue = $columnDefinition->getDefaultValue();

        if (!is_null($defaultValue)) {
            if (!$dataType->mustResolveDefaultValue()) {
                # default value must be resolved at insertion-time => save unresolved
                $defaultValueData = $this->valueResolver->resolveValue($defaultValue, $executionContext);
                $defaultValueData = $this->dataConverter->convertStringToBinary(
                    $defaultValueData,
                    $columnPage->getDataType()
                );
            } else {
                $defaultValueData = (string)$defaultValue;
            }
        } else {
            $defaultValueData = null;
        }

        $columnPage->setDefaultValue($defaultValueData);

        $comment = $columnDefinition->getComment();

        # TODO: save column comment

        return $columnPage;
    }

}
