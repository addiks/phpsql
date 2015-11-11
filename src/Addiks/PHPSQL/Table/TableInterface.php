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

use Addiks\PHPSQL\Iterators\DataProviderInterface;
use Addiks\PHPSQL\StatementExecutor\ExecutionContext;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Column\ColumnDataInterface;

interface TableInterface extends DataProviderInterface
{
    
    /**
     *
     * @return TableSchema
     */
    public function getTableSchema();
    
    public function addColumn(ColumnSchema $columnSchema, ColumnDataInterface $columnData);

    public function modifyColumn(ColumnSchema $columnSchema, ColumnDataInterface $columnData);

    public function getCellData($rowId, $columnId);
    
    public function setCellData($rowId, $columnId, $data);
    
    public function setRowData($rowId, array $rowData);
    
    public function addRowData(array $rowData);
    
    public function removeRow($rowId);
    
}
