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

class SchemataInformationSchemaTable extends InformationSchemaTable
{

    ### DATA-PROVIDER-INTERFACE

    public function doesRowExists($rowId = null)
    {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $schemas = $schemaManager->listSchemas();

        return isset($schemas[$rowId]);
    }

    public function getRowData($rowId = null)
    {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $schemas = $schemaManager->listSchemas();

        $schema = null;
        if (isset($schemas[$rowId])) {
            $schema = $schemas[$rowId];
        }

        return [
            'CATALOG_NAME' => null,
            'SCHEMA_NAME' => $schema,
            'DEFAULT_CHARACTER_SET_NAME' => null,
            'DEFAULT_COLLATION_NAME' => null,
            'SQL_PATH' => null,
        ];
    }

    public function getCellData($rowId, $columnId)
    {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $schemas = $schemaManager->listSchemas();

        $schema = null;
        if (isset($schemas[$rowId])) {
            $schema = $schemas[$rowId];
        }

        return $schema;
    }

    public function tell()
    {
        return $this->index;
    }

    ### COUNTABLE

    public function count()
    {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $schemas = $schemaManager->listSchemas();

        return count($schemas);
    }

    ### SEEKABLE ITERATOR

    protected $index;

    public function seek($position)
    {
        if ($position >= 0 && $position < $this->count()) {
            $this->index = $position;
        }
    }

    public function rewind()
    {
        if ($this->count() > 0) {
            $this->index = 0;
        } else {
            $this->index = null;
        }
    }

    public function valid()
    {
        return !is_null($this->index);
    }

    public function current()
    {
        $row = null;

        if ($this->valid()) {
            /* @var $schemaManager SchemaManager */
            $schemaManager = $this->schemaManager;

            $schemas = $schemaManager->listSchemas();

            $schema = $schemas[$this->index];

            $row = [
                'CATALOG_NAME' => null,
                'SCHEMA_NAME' => $schema,
                'DEFAULT_CHARACTER_SET_NAME' => null,
                'DEFAULT_COLLATION_NAME' => null,
                'SQL_PATH' => null,
            ];
        }

        return $row;
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        if ($this->valid() && $this->index < $this->count()) {
            $this->index += 1;

            if ($this->index >= $this->count()) {
                $this->index = null;
            }
        }
    }

}
