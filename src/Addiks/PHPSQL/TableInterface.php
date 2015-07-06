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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition;

interface TableInterface
{
    
    public function __construct($tableName, $schemaId = null);
    
    public function getDBSchemaId();
    
    public function getDBSchema();
    
    public function getTableName();
    
    public function getTableId();
    
    /**
     *
     * @return TableSchema
     */
    public function getTableSchema();
    
    public function addColumnDefinition(ColumnDefinition $columnDefinition);
    
    public function getIterator();
    
    public function getCellData($rowId, $columnId);
    
    public function setCellData($rowId, $columnId, $data);
    
    public function getRowData($rowId = null);
    
    public function setRowData($rowId, array $rowData);
    
    public function addRowData(array $rowData);
    
    public function removeRow($rowId);
    
    public function getRowExists($rowId = null);
    
    public function count();
    
    public function seek($rowId);
    
    public function convertStringRowToDataRow(array $row);
    
    public function convertDataRowToStringRow(array $row);
    
}
