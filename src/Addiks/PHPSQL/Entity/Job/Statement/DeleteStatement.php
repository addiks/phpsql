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

namespace Addiks\PHPSQL\Entity\Job\Statement;

use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Executor\DeleteExecutor;
use Addiks\PHPSQL\Value\Specifier\TableSpecifier;
use Addiks\PHPSQL\Entity\Job\Part\ValuePart;
use Addiks\PHPSQL\Entity\Job\Part\Join;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

/**
 *
 */
class DeleteStatement extends StatementJob
{
    
    const EXECUTOR_CLASS = DeleteExecutor::class;

    private $deleteTables = array();
    
    public function addDeleteTable(TableSpecifier $table)
    {
        $this->deleteTables[] = $table;
    }
    
    public function getDeleteTables()
    {
        return $this->deleteTables;
    }
    
    private $joinDefinition;
    
    public function setJoinDefinition(Join $join)
    {
        $this->joinDefinition = $join;
    }
    
    public function getJoinDefinition()
    {
        return $this->joinDefinition;
    }
    
    private $condition;
    
    public function setCondition(ValuePart $condition)
    {
        $this->condition = $condition;
    }
    
    public function getCondition()
    {
        return $this->condition;
    }
    
    public function getResultSpecifier()
    {
    }
    
    private $orderColumn;
    
    public function setOrderColumn(ColumnSpecifier $column)
    {
        $this->orderColumn = $column;
    }
    
    public function getOrderColumn()
    {
        return $this->orderColumn;
    }
    
    private $orderDirection;
    
    public function setOrderDirection(SqlToken $direction)
    {
        $this->orderDirection = $direction === SqlToken::T_ASC() ?$direction :SqlToken::T_DESC();
    }
    
    public function getOrderDirection()
    {
        return $this->orderDirection;
    }
    
    private $limitOffset;
    
    public function setLimitOffset($offset)
    {
        $this->limitOffset = (int)$offset;
    }
    
    public function getLimitOffset()
    {
        return $this->limitOffset;
    }
    
    private $limitRowCount;
    
    public function setLimitRowCount($count)
    {
        $this->limitRowCount = (int)$count;
    }
    
    public function getLimitRowCount()
    {
        return $this->limitRowCount;
    }
}
