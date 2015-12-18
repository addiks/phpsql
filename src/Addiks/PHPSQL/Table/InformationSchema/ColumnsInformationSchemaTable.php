<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Table\InformationSchema;

use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;
use Addiks\PHPSQL\Database\DatabaseSchema;

class ColumnsInformationSchemaTable extends InformationSchemaTable
{
    protected $allColumns;

    public function clearCache()
    {
        $this->allColumns = null;
    }

    protected function getAllColumns()
    {
        if (is_null($this->allColumns)) {
            /* @var $schemaManager SchemaManager */
            $schemaManager = $this->schemaManager;

            $schemas = $schemaManager->listSchemas();

            $this->allColumns = array();
            foreach ($schemas as $schemaId) {
                /* @var $schema DatabaseSchema */
                $schema = $schemaManager->getSchema($schemaId);

                foreach ($schema->listTables() as $tableId => $tableName) {
                    /* @var $tablePage DatabaseSchemaPage */
                    $tablePage = $schema->getTablePage($tableId);

                    /* @var $tableSchema TableSchema */
                    $tableSchema = $schemaManager->getTableSchema($tableId, $schemaId);

                    foreach ($tableSchema->getColumnIterator() as $columnSchema) {
                        /* @var $columnSchema ColumnSchema */

                        $this->allColumns[] = [
                            'TABLE_CATALOG'            => null,
                            'TABLE_SCHEMA'             => $schemaId,
                            'TABLE_NAME'               => $tableName,
                            'COLUMN_NAME'              => $columnSchema->getName(),
                            'ORDINAL_POSITION'         => $columnSchema->getIndex(),
                            'COLUMN_DEFAULT'           => $columnSchema->getDefaultValue(),
                            'IS_NULLABLE'              => $columnSchema->isNotNull() ?'0' :'1',
                            'DATA_TYPE'                => $columnSchema->getDataType()->getName(),
                            'CHARACTER_MAXIMUM_LENGTH' => null,
                            'CHARACTER_OCTET_LENGTH'   => null,
                            'NUMERIC_PRECISION'        => null,
                            'NUMERIC_SCALE'            => null,
                            'DATETIME_PRECISION'       => null,
                            'CHARACTER_SET_NAME'       => null,
                            'COLLATION_NAME'           => null,
                            'COLUMN_TYPE'              => null,
                            'COLUMN_KEY'               => null,
                            'EXTRA'                    => null,
                            'PRIVILEGES'               => null,
                            'COLUMN_COMMENT'           => null,
                        ];
                    }
                }
            }
        }

        return $this->allColumns;
    }

    ### DATA-PROVIDER-INTERFACE

    public function doesRowExists($rowId = null)
    {
        return $rowId < $this->count();
    }

    public function getRowData($rowId = null)
    {
        $rows = $this->getAllColumns();

        $row = null;
        if (isset($rows[$rowId])) {
            $row = $rows[$rowId];
        }

        return $row;
    }

    public function getCellData($rowId, $columnId)
    {
        $cell = null;
        if ($this->valid() && $columnId < 19) {
            $cell = $this->getRowData($rowId)[$columnId];
        }
        return $cell;
    }

    ### COUNTABLE

    public function count()
    {
        return count($this->getAllColumns());
    }

    public function current()
    {
        $row = null;
        if ($this->valid()) {
            $row = $this->getRowData($this->key());
        }

        return $row;
    }

}
