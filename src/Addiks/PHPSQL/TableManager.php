<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Value\Specifier\DatabaseSpecifier;
use Addiks\PHPSQL\Table;
use Addiks\PHPSQL\Index;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Entity\Page\Schema\IndexPage;

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

    protected $tables = array();

    public function getTable($tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }
        $tableId = "{$schemaId}.{$tableName}";
        if (!isset($this->tables[$tableId])) {
            $this->tables[$tableId] = new Table(
                $this->schemaManager,
                $this->filesystem,
                $tableName,
                $schemaId
            );
        }
        return $this->tables[$tableId];
    }

    public function createTable(TableSchema $tableSchema)
    {
        unimplemented();
    }

    ### INDICIES

    protected $indicies = array();

    public function getIndex($indexName, $tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }
        $indexId = "{$schemaId}.{$tableName}.{$indexName}";
        if (!isset($this->indicies[$indexId])) {
            $this->indicies[$indexId] = new Index(
                $this->filesystem,
                $this->schemaManager,
                $indexId,
                $tableName,
                $schemaId
            );
        }
        return $this->indicies[$indexId];
    }

    public function createIndex(IndexPage $indexPage, $tableName, $schemaId = null)
    {
        unimplemented();

        $indexPosition = $tableSchema->addIndexPage($indexSchemaPage);
    }

}
