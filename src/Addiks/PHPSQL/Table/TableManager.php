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

namespace Addiks\PHPSQL\Table;

use ErrorException;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Value\Specifier\DatabaseSpecifier;
use Addiks\PHPSQL\Index;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Table\Table;
use Addiks\PHPSQL\Table\Meta\InformationSchema;
use Addiks\PHPSQL\Table\Meta\MySQLTable;
use Addiks\PHPSQL\Table\Meta\InternalIndices;
use Addiks\PHPSQL\Table\TableSchemaInterface;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;
use Addiks\PHPSQL\Column\ColumnDataInterface;
use Addiks\PHPSQL\Table\TableFactoryInterface;
use Addiks\PHPSQL\Column\ColumnDataFactoryInterface;
use Addiks\PHPSQL\Index\IndexFactory;

class TableManager
{
    public function __construct(
        FilesystemInterface $filesystem,
        SchemaManager $schemaManager
    ) {
        $this->filesystem = $filesystem;
        $this->schemaManager = $schemaManager;
    }

    protected $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    ### TABLES

    protected $metaTables = array(
        SchemaManager::DATABASE_ID_META_INDICES            => InternalIndices::class,
        SchemaManager::DATABASE_ID_META_MYSQL              => MySQLTable::class,
        SchemaManager::DATABASE_ID_META_INFORMATION_SCHEMA => InformationSchema::class
    );

    protected $tables = array();

    public function getTable($tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }

        $tableId = "{$schemaId}.{$tableName}";
        if (!isset($this->tables[$tableId])) {
            if (isset($this->metaTables[$schemaId])) {
                $table = $this->metaTables[$schemaId];

                if (is_string($table)) {
                    $table = new $table($tableName, $schemaId);

                    $this->metaTables[$schemaId] = $table;
                }

            } else {
                /* @var $databaseSchema DatabaseSchemaInterface */
                $databaseSchema = $this->schemaManager->getSchema($schemaId);

                $tableSchema = $this->schemaManager->getTableSchema($tableName, $schemaId);
                
                $tableIndex = $databaseSchema->getTableIndex($tableName);

                /* @var $databaseSchemaPage DatabaseSchemaPage */
                $databaseSchemaPage = $databaseSchema->getTablePage($tableIndex, $schemaId);
                
                /* @var $engine Engine */
                $engine = $databaseSchemaPage->getEngine();

                # TODO: fix up this scattered schema-madness above! That should not be neccessary!

                if (!isset($this->tableFactories[(string)$engine])) {
                    throw new ErrorException("Missing table-factory for table-engine '{$engine}'!");
                }

                if (!isset($this->columnDataFactories[(string)$engine])) {
                    throw new ErrorException("Missing column-data-factory for table-engine '{$engine}'!");
                }

                /* @var $tableFactory TableFactoryInterface */
                $tableFactory = $this->tableFactories[(string)$engine];

                /* @var $columnDataFactory  */
                $columnDataFactory = $this->columnDataFactories[(string)$engine];

                $indexFactory = $this->getIndexFactory();

                /* @var $table TableInterface */
                $table = $tableFactory->createTable(
                    $schemaId,
                    $tableIndex,
                    $tableSchema,
                    $columnDataFactory,
                    $indexFactory
                );
            }

            $this->tables[$tableId] = $table;
        }

        return $this->tables[$tableId];
    }

    public function getTableIdByName($tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }
        
        /* @var $databaseSchema DatabaseSchemaInterface */
        $databaseSchema = $this->schemaManager->getSchema($schemaId);

        $tableIndex = $databaseSchema->getTableIndex($tableName);

        return $tableIndex;
    }

    protected $columnDataFactories;

    public function getColumnDataFactories()
    {
        return $this->columnDataFactories;
    }

    /**
     * @param  string                     $tableName
     * @param  string                     $schemaId
     * @return ColumnDataFactoryInterface
     */
    public function getColumnDataFactory($tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }
        
        /* @var $databaseSchema DatabaseSchemaInterface */
        $databaseSchema = $this->schemaManager->getSchema($schemaId);

        $tableIndex = $databaseSchema->getTableIndex($tableName);

        /* @var $databaseSchemaPage DatabaseSchemaPage */
        $databaseSchemaPage = $databaseSchema->getTablePage($tableIndex, $schemaId);
        
        /* @var $engine Engine */
        $engine = $databaseSchemaPage->getEngine();

        /* @var $columnDataFactory  */
        $columnDataFactory = $this->columnDataFactories[(string)$engine];

        return $columnDataFactory;
    }

    protected $tableFactories;

    public function getTableFactories()
    {
        return $this->tableFactories;
    }
    
    public function registerFactories(
        Engine $engine,
        TableFactoryInterface $tableFactory,
        ColumnDataFactoryInterface $columnDataFactory
    ) {
        $this->tableFactories[(string)$engine] = $tableFactory;
        $this->columnDataFactories[(string)$engine] = $columnDataFactory;
    }

    public function unregisterFactories(Engine $engine)
    {
        if (isset($this->tableFactories[(string)$engine])) {
            unset($this->tableFactories[(string)$engine]);
        }
        if (isset($this->columnDataFactories[(string)$engine])) {
            unset($this->columnDataFactories[(string)$engine]);
        }
    }

    protected $indexFactory;

    public function getIndexFactory()
    {
        if (is_null($this->indexFactory)) {
            $this->indexFactory = new IndexFactory($this->filesystem);
        }
        return $this->indexFactory;
    }

}
