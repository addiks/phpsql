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

namespace Addiks\PHPSQL\Resource\Table\Meta;

use Addiks\Depencies\Resource\Context;

use Addiks\Common\Resource;

use Addiks\PHPSQL\Resource\Table;
use Addiks\PHPSQL\Entity\Index\IndexInterface;

use Addiks\Common\Resource;

/**
 *
 * @Addiks\Factory(static=true, method="self::staticFactory")
 */
abstract class InformationSchema extends Resource implements IndexInterface
{
    
    public static function staticFactory(Context $context, $tableName, $schemaId = null)
    {
    
        switch($tableName){
                
            case 'TABLES':
                /* @var $tableResource Table */
                $context->factorize($tableResource, [$tableName, $schemaId]);
                break;
    
            default:
                throw new Error("Unknown table '{$tableName}' in meta-database 'information_schema'!");
        }
    
        return $tableResource;
    }
    
    public function __construct($tableName, $schemaId = null)
    {
        
        $this->schemaId = $schemaId;
        $this->tableName = $tableName;
    }
    
    private $index;
    
    public function getIndex()
    {
        return $this->index;
    }
    
    private $schemaId;
    
    public function getDBSchemaId()
    {
        return $this->schemaId;
    }
    
    public function getDBSchema()
    {
    
    }
    
    private $tableName;
    
    public function getTableName()
    {
        return $this->tableName;
    }
    
    public function getTableId()
    {
        
    }
    
    private $tableSchema;
    
    public function getTableSchema()
    {
        if (is_null($this->tableSchema)) {
        }
        return $this->tableSchema;
    }
    
    private $iterator;
    
    public function getIterator()
    {
    
        if (is_null($this->iterator)) {
            $this->iterator = new \ArrayIterator(array());
        }
        return $this->iterator;
    }
    
    public function getCellData($rowId, $columnId)
    {
        return null;
    }
    
    public function setCellData($rowId, $columnId, $data)
    {
    
    }
    
    public function getRowData($rowId = null)
    {
        return null;
    }
    
    public function setRowData($rowId, array $rowData)
    {
    
    }
    
    public function addRowData(array $rowData)
    {
    
    }
    
    public function removeRow($rowId)
    {
        
    }
    
    public function getRowExists($rowId = null)
    {
        return false;
    }
    
    public function count()
    {
        return 0;
    }
    
    public function seek($rowId)
    {
    
    }
    
    public function convertStringRowToDataRow(array $row)
    {
        return $row;
    }
    
    public function convertDataRowToStringRow(array $row)
    {
        return $row;
    }
}
