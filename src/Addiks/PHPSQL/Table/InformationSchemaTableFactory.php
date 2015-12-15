<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Table;

use Addiks\PHPSQL\Table\TableSchemaInterface;
use Addiks\PHPSQL\Index\IndexFactoryInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Column\ColumnDataInterface;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Table\InformationSchema\TablesInformationSchemaTable;
use Addiks\PHPSQL\Table\InformationSchema\ColumnsInformationSchemaTable;
use Addiks\PHPSQL\Table\InformationSchema\EnginesInformationSchemaTable;
use Addiks\PHPSQL\Table\InformationSchema\SchemataInformationSchemaTable;
use Addiks\PHPSQL\Table\InformationSchema\ViewsInformationSchemaTable;

class InformationSchemaTableFactory implements TableFactoryInterface
{

    public function __construct(SchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    /**
     *
     * @param  integer        $tableId
     * @return TableInterface
     */
    public function createTable(
        $schemaId,
        $tableId,
        TableSchemaInterface $tableSchema,
        IndexFactoryInterface $indexFactory
    ) {
        $table = null;

        switch ($tableId) {
            case 0: # COLUMNS
                $table = new ColumnsInformationSchemaTable($this->schemaManager, $tableSchema);
                break;

            case 1: # ENGINES
                $table = new EnginesInformationSchemaTable($this->schemaManager, $tableSchema);
                break;

            case 2: # SCHEMATA
                $table = new SchemataInformationSchemaTable($this->schemaManager, $tableSchema);
                break;

            case 3: # TABLES
                $table = new TablesInformationSchemaTable($this->schemaManager, $tableSchema);
                break;

            case 4: # VIEWS
                $table = new ViewsInformationSchemaTable($this->schemaManager, $tableSchema);
                break;
        }

        return $table;
    }

    public function addColumnToTable(
        $schemaId,
        $tableId,
        $columnId,
        TableInterface $table,
        ColumnSchema $columnSchema
    ) {
    }


    public function modifyColumnOnTable(
        $schemaId,
        $tableId,
        $columnId,
        TableInterface $table,
        ColumnSchema $columnSchema
    ) {
    }

}
