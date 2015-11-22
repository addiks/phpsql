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

namespace Addiks\PHPSQL\StatementExecutor;

use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Table\TableInterface;
use InvalidArgumentException;
use Addiks\PHPSQL\Result\ResultInterface;
use Addiks\PHPSQL\Iterators\DataProviderInterface;

/**
 * Contains information about and for the currently executed statement.
 */
class ExecutionContext
{
    public function __construct(
        SchemaManager $schemaManager,
        StatementJob $statement,
        array $parameters = array()
    ) {
        $this->schemaManager = $schemaManager;
        $this->statement = $statement;
        $this->parameters = $parameters;
    }

    /**
     * The statement-parameters for the currently executed statement.
     *
     * @var array
     */
    protected $parameters = array();

    public function getParameters()
    {
        return $this->parameters;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    /**
     * The statement currently being executed.
     *
     * @var StatementJob
     */
    protected $statement;

    public function getStatement()
    {
        return $this->statement;
    }
    /**
     * A container of table-resources to act as data-source for the execution.
     *
     * @var array
     */
    protected $tables = array();

    public function getTables()
    {
        return $this->tables;
    }

    public function getTable($alias)
    {
        if (!isset($this->tables[$alias])) {
            throw new InvalidArgumentException("Table '{$alias}' does not exist!");
        }

        return $this->tables[$alias];
    }

    public function setTable(DataProviderInterface $table, $alias)
    {
        $this->tables[$alias] = $table;
    }

    /**
     * The current unprocessed data-row fetched from the tables to be processed.
     *
     * @var array
     */
    protected $currentSourceRow = array();

    public function setCurrentSourceRow(array $currentSourceRow)
    {
        $this->currentSourceRow = $currentSourceRow;
    }

    public function getCurrentSourceRow()
    {
        return $this->currentSourceRow;
    }

    protected $currentSourceSet;

    public function setCurrentSourceSet(ResultInterface $currentSourceSet)
    {
        $this->currentSourceSet = $currentSourceSet;
    }

    public function getCurrentSourceSet()
    {
        return $this->currentSourceSet;
    }

    protected $currentResultSet;

    public function setCurrentResultSet(ResultInterface $currentResultSet)
    {
        $this->currentResultSet = $currentResultSet;
    }

    public function getCurrentResultSet()
    {
        return $this->currentResultSet;
    }

}
