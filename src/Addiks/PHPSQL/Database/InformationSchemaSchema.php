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

namespace Addiks\PHPSQL\Database;

use Addiks\PHPSQL\Database\DatabaseSchemaInterface;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Type;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Column\ColumnSchema;

class InformationSchemaSchema implements DatabaseSchemaInterface
{

    public function __construct(
        SchemaManager $schemaManager
    ) {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    ### TABLES

    public function listTables()
    {
        $tables = array();

        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $tables[] = "COLUMNS";
        $tables[] = "ENGINES";
        $tables[] = "SCHEMATA";
        $tables[] = "TABLES";
        $tables[] = "VIEWS";

        return $tables;
    }

    public function tableExists($tableName)
    {
        return in_array($tableName, $this->listTables());
    }

    public function getTableIndex($tableName)
    {
        return array_search($tableName, $this->listTables());
    }

    public function registerTable($tableName)
    {
    }

    public function registerTableSchema(DatabaseSchemaPage $schemaPage)
    {
    }

    public function unregisterTable($tableName)
    {
    }

    public function getTablePage($tableId)
    {
        $entity = new DatabaseSchemaPage();
        $entity->setName($tableId);
        $entity->setCollation("latin1_bin");
        $entity->setEngine(Engine::INFORMATION_SCHEMA());
        $entity->setType(Type::TABLE());

        return $entity;
    }

    public function createTableSchema(
        $tableSchemaFile,
        $indexSchemaFile,
        $tableName
    ) {
        $tableSchema = new TableSchema(
            new FileResourceProxy(fopen("php://memory", "w")),
            new FileResourceProxy(fopen("php://memory", "w"))
        );

        $columns = array();

        $columnsMap = array(
            'COLUMNS' => [
                ['TABLE_CATALOG', DataType::VARCHAR(), 32],
                ['TABLE_SCHEMA', DataType::VARCHAR(), 32],
                ['TABLE_NAME', DataType::VARCHAR(), 32],
                ['COLUMN_NAME', DataType::VARCHAR(), 32],
                ['ORDINAL_POSITION', DataType::VARCHAR(), 32],
                ['COLUMN_DEFAULT', DataType::VARCHAR(), 32],
                ['IS_NULLABLE', DataType::VARCHAR(), 32],
                ['DATA_TYPE', DataType::VARCHAR(), 32],
                ['CHARACTER_MAXIMUM_LENGTH', DataType::VARCHAR(), 32],
                ['CHARACTER_OCTET_LENGTH', DataType::VARCHAR(), 32],
                ['NUMERIC_PRECISION', DataType::VARCHAR(), 32],
                ['NUMERIC_SCALE', DataType::VARCHAR(), 32],
                ['DATETIME_PRECISION', DataType::VARCHAR(), 32],
                ['CHARACTER_SET_NAME', DataType::VARCHAR(), 32],
                ['COLLATION_NAME', DataType::VARCHAR(), 32],
                ['COLUMN_TYPE', DataType::VARCHAR(), 32],
                ['COLUMN_KEY', DataType::VARCHAR(), 32],
                ['EXTRA', DataType::VARCHAR(), 32],
                ['PRIVILEGES', DataType::VARCHAR(), 32],
                ['COLUMN_COMMENT', DataType::VARCHAR(), 32],
            ],
            'ENGINES' => [
                ['ENGINE', DataType::VARCHAR(), 32],
                ['SUPPORT', DataType::VARCHAR(), 32],
                ['COMMENT', DataType::VARCHAR(), 32],
                ['TRANSACTIONS', DataType::VARCHAR(), 32],
                ['XA', DataType::VARCHAR(), 32],
                ['SAVEPOINTS', DataType::VARCHAR(), 32],
            ],
            'SCHEMATA' => [
                ['CATALOG_NAME', DataType::VARCHAR(), 32],
                ['SCHEMA_NAME', DataType::VARCHAR(), 32],
                ['DEFAULT_CHARACTER_SET_NAME', DataType::VARCHAR(), 32],
                ['DEFAULT_COLLATION_NAME', DataType::VARCHAR(), 32],
                ['SQL_PATH', DataType::VARCHAR(), 32],
            ],
            'TABLES' => [
                ['TABLE_CATALOG', DataType::VARCHAR(), 32],
                ['TABLE_SCHEMA', DataType::VARCHAR(), 32],
                ['TABLE_NAME', DataType::VARCHAR(), 32],
                ['TABLE_TYPE', DataType::VARCHAR(), 32],
                ['ENGINE', DataType::VARCHAR(), 32],
                ['VERSION', DataType::VARCHAR(), 32],
                ['ROW_FORMAT', DataType::VARCHAR(), 32],
                ['TABLE_ROWS', DataType::VARCHAR(), 32],
                ['AVG_ROW_LENGTH', DataType::VARCHAR(), 32],
                ['DATA_LENGTH', DataType::VARCHAR(), 32],
                ['MAX_DATA_LENGTH', DataType::VARCHAR(), 32],
                ['INDEX_LENGTH', DataType::VARCHAR(), 32],
                ['DATA_FREE', DataType::VARCHAR(), 32],
                ['AUTO_INCREMENT', DataType::VARCHAR(), 32],
                ['CREATE_TIME', DataType::VARCHAR(), 32],
                ['UPDATE_TIME', DataType::VARCHAR(), 32],
                ['CHECK_TIME', DataType::VARCHAR(), 32],
                ['TABLE_COLLATION', DataType::VARCHAR(), 32],
                ['CHECKSUM', DataType::VARCHAR(), 32],
                ['CREATE_OPTIONS', DataType::VARCHAR(), 32],
                ['TABLE_COMMENT', DataType::VARCHAR(), 32],
            ],
            'VIEWS' => [
                ['TABLE_CATALOG', DataType::VARCHAR(), 32],
                ['TABLE_SCHEMA', DataType::VARCHAR(), 32],
                ['TABLE_NAME', DataType::VARCHAR(), 32],
                ['VIEW_DEFINITION', DataType::VARCHAR(), 32],
                ['CHECK_OPTION', DataType::VARCHAR(), 32],
                ['IS_UPDATABLE', DataType::VARCHAR(), 32],
                ['DEFINER', DataType::VARCHAR(), 32],
                ['SECURITY_TYPE', DataType::VARCHAR(), 32],
                ['CHARACTER_SET_CLIENT', DataType::VARCHAR(), 32],
                ['COLLATION_CONNECTION', DataType::VARCHAR(), 32],
            ],
        );

        if (isset($columnsMap[$tableName])) {
            $columns = $columnsMap[$tableName];
        }

        foreach ($columns as $index => list($name, $type, $length)) {
            $columnSchema = new ColumnSchema();
            $columnSchema->setName($name);
            $columnSchema->setIndex($index);
            $columnSchema->setDataType($type);
            $columnSchema->setLength($length);

            $tableSchema->addColumnSchema($columnSchema);
        }

        return $tableSchema;
    }

    ### VIEWS

    public function listViews()
    {
    }

    public function viewExists($viewName)
    {
    }

    public function getViewIndex($viewName)
    {
    }

    public function registerView($viewName)
    {
    }

    public function unregisterView($viewName)
    {
    }
}
