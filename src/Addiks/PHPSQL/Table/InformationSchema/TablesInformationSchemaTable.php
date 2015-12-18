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
use Addiks\PHPSQL\Database\DatabaseSchemaPage;
use Addiks\PHPSQL\Database\DatabaseSchema;

class TablesInformationSchemaTable extends InformationSchemaTable
{

    protected function getAllTables()
    {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $schemas = $schemaManager->listSchemas();

        $allTables = array();

        foreach ($schemas as $schemaId) {
            /* @var $schema DatabaseSchema */
            $schema = $schemaManager->getSchema($schemaId);

            foreach ($schema->listTables() as $tableId => $tableName) {
                /* @var $tablePage DatabaseSchemaPage */
                $tablePage = $schema->getTablePage($tableId);

                $allTables[] = [
                    'TABLE_CATALOG' => null,
                    'TABLE_SCHEMA' => $schemaId,
                    'TABLE_NAME' => $tableName,
                    'TABLE_TYPE' => null,
                    'ENGINE' => $tablePage->getEngine()->getName(),
                    'VERSION' => null,
                    'ROW_FORMAT' => null,
                    'TABLE_ROWS' => null,
                    'AVG_ROW_LENGTH' => null,
                    'DATA_LENGTH' => null,
                    'MAX_DATA_LENGTH' => null,
                    'INDEX_LENGTH' => null,
                    'DATA_FREE' => null,
                    'AUTO_INCREMENT' => null,
                    'CREATE_TIME' => null,
                    'UPDATE_TIME' => null,
                    'CHECK_TIME' => null,
                    'TABLE_COLLATION' => null,
                    'CHECKSUM' => null,
                    'CREATE_OPTIONS' => null,
                    'TABLE_COMMENT' => null,
                ];
            }
        }

        return $allTables;
    }

    ### DATA-PROVIDER-INTERFACE

    public function doesRowExists($rowId = null)
    {
        return $rowId < $this->count();
    }

    public function getRowData($rowId = null)
    {
        if ($this->doesRowExists($rowId)) {
            return $this->getAllTables()[$rowId];
        }
    }

    public function getCellData($rowId, $columnId)
    {
        $cell = null;

        if ($this->doesRowExists($rowId) && $columnId <= 20) {
            $row = $this->getRowData($rowId);

            $keys = array_keys($row);
            $key = $keys[$columnId];

            $cell = $row[$key];
        }

        return $cell;
    }

    ### COUNTABLE

    public function count()
    {
        return count($this->getAllTables());
    }

    ### SEEKABLE ITERATOR

    public function current()
    {
        $row = null;

        if ($this->valid()) {
            $row = $this->getRowData($this->index);
        }

        return $row;
    }

}
