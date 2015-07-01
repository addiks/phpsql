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

namespace Addiks\PHPSQL\Entity\TableSchema\Meta;

use Addiks\Common\Entity;

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Common\Entity;

use Addiks\Depencies\Resource\Context;

use Addiks\PHPSQL\TableSchemaInterface;
use Addiks\PHPSQL\Entity\Index\IndexInterface;
use Addiks\PHPSQL\Entity\TableSchema\Meta\InformationSchema\Tables;
use Addiks\PHPSQL\Entity\SchemaInterface;

/**
 *
 * @Addiks\Factory(static=true, method="self::staticFactory")
 */
abstract class InformationSchema extends Entity implements IndexInterface
{

    public static function staticFactory(Context $context, $tableSchemaStorage, $indexSchemaStorage, $tableName)
    {
        
        switch($tableName){
            
            case 'TABLES':
                /* @var $tableSchema Tables */
                $context->factorize($tableSchema, [$tableSchemaStorage, $indexSchemaStorage]);
                break;
                
            default:
                throw new Error("Unknown table '{$tableName}' in meta-database 'information_schema'!");
        }
        
        return $tableSchema;
    }
    
    private $databaseSchema;

    public function setDatabaseSchema(SchemaInterface $schema)
    {
        $this->databaseSchema = $schema;
    }

    public function getDatabaseSchema()
    {
        return $this->databaseSchema;
    }

    private $keyLength;

    public function getKeyLength()
    {
        return $this->keyLength;
    }

    public function setKeyLength($keyLength)
    {
        $this->keyLength = $keyLength;
    }

    ### COLUMNS

    private $columns;

    abstract protected function getInternalColumns();

    public function getColumnIterator()
    {
        return $this->getInternalColumns();
    }

    public function getColumnIndex($columnName)
    {
        foreach ($this->getInternalColumns() as $index => $columnPage) {
            if ($columnPage->getName() === $columnName) {
                return $index;
            }
        }
    }

    public function getCachedColumnIds()
    {
        return array_keys($this->getInternalColumns());
    }

    public function dropColumnCache()
    {
    }

    public function getPrimaryKeyColumns()
    {

        return $this->getInternalColumns();
    }

    public function listColumns()
    {
        return $this->getColumnIterator();
    }

    public function getColumn($index)
    {
        return $this->getInternalColumns()[$this->getCachedColumnIds()[$index]];
    }

    public function columnExist($columnName)
    {
        return !is_null($this->getColumnIndex($columnName));
    }

    ### INDICIES

    public function getIndexIterator()
    {
    }

    public function indexExist($name)
    {
    }

    public function getIndexIdByColumns($columnIds)
    {
    }

    public function addIndexPage(Index $indexPage)
    {
    }

    public function getIndexPage($index)
    {
    }

    public function getLastIndex()
    {
    }

    ### COLUMNS MODIFIERS

    public function addColumnPage(Column $column)
    {
    }

    public function writeColumn($index = null, Column $column)
    {
    }
}
