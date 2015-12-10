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
            case 'TABLES':
                $table = new TablesInformationSchemaTable($this->schemaManager);
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
