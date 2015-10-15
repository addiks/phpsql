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

namespace Addiks\PHPSQL\Job\Statement\Create;

use Addiks\PHPSQL\Job\Statement\CreateStatement;

use Addiks\PHPSQL\Value\Enum\Sql\IndexType;

use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;

use Addiks\PHPSQL\Value\Specifier\TableSpecifier as TableSpecifier;

use Addiks\PHPSQL\Job\Statement\Create;


use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Executor\CreateIndexExecutor;

/**
 *
 */
class CreateIndexStatement extends CreateStatement
{
    
    const EXECUTOR_CLASS = CreateIndexExecutor::class;

    private $isUnique = false;
    
    public function setIsUnique($bool)
    {
        $this->isUnique = (bool)$bool;
    }
    
    public function getIsUnique()
    {
        return $this->isUnique;
    }
    
    private $isPrimary = false;
    
    public function setIsPrimary($bool)
    {
        $this->isPrimary = (bool)$bool;
    }
    
    public function getIsPrimary()
    {
        return $this->isPrimary;
    }
    
    private $indexType;
    
    public function setIndexType(IndexType $type)
    {
        $this->indexType = $type;
    }
    
    public function getIndexType()
    {
        if (is_null($this->indexType)) {
            $this->indexType = IndexType::BTREE();
        }
        return $this->indexType;
    }
    
    private $table;
    
    public function setTable(TableSpecifier $table)
    {
        $this->table = $table;
    }
    
    public function getTable()
    {
        return $this->table;
    }
    
    private $columns = array();
    
    public function getColumns()
    {
        return $this->columns;
    }
    
    public function addColumn(ColumnSpecifier $column, $length = null, $direction = null)
    {
        
        $direction = ($direction === SqlToken::T_DESC()) ?SqlToken::T_DESC() :SqlToken::T_ASC();
        
        $this->columns[] = [
            'column' => $column,
            'length' => $length,
            'direction' => $direction,
        ];
    }
}
