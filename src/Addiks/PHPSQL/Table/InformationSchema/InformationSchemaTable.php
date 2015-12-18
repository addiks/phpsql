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
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Column\ColumnDataInterface;
use Addiks\PHPSQL\Table\TableSchemaInterface;

abstract class InformationSchemaTable implements TableInterface
{

    public function __construct(
        SchemaManager $schemaManager,
        TableSchemaInterface $tableSchema
    ) {
        $this->schemaManager = $schemaManager;
        $this->tableSchema = $tableSchema;
    }

    protected $schemaManager;

    protected $tableSchema;

    public function getTableSchema()
    {
        return $this->tableSchema;
    }

    ### TABLE-INTERFACE

    final public function addColumn(ColumnSchema $columnSchema, ColumnDataInterface $columnData)
    {
    }

    final public function modifyColumn(ColumnSchema $columnSchema, ColumnDataInterface $columnData)
    {
    }

    final public function setRowData($rowId, array $rowData)
    {
    }

    final public function addRowData(array $rowData)
    {
    }

    final public function setCellData($rowId, $columnId, $data)
    {
    }

    final public function removeRow($rowId)
    {
    }

    ### ITERATOR

    protected $index;

    public function tell()
    {
        return $this->index;
    }

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
