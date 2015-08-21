<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL;

use Iterator;
use SeekableIterator;
use Countable;
use Addiks\PHPSQL\Entity\Job\Part\ValuePart;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Result\ResultInterface;
use Addiks\PHPSQL\TableInterface;
use Addiks\PHPSQL\Entity\ExecutionContext;

class FilteredResourceIterator implements SeekableIterator, Countable, UsesBinaryDataInterface
{
    public function __construct(
        Iterator $tableResource,
        ValuePart $condition,
        ValueResolver $valueResolver,
        ExecutionContext $executionContext
    ) {
        $this->tableResource = $tableResource;
        $this->condition = $condition;
        $this->valueResolver = $valueResolver;
        $this->executionContext = clone $executionContext;
    }

    /**
     * @var Iterator
     */
    protected $tableResource;

    /**
     * @var ValuePart
     */
    protected $condition;

    /**
     * @var ValueResolver
     */
    protected $valueResolver;

    /**
     * @var ExecutionContext
     */
    protected $executionContext;

    public function rewind()
    {
        $this->tableResource->rewind();
        $this->skipNotMatchingRows();
    }

    public function valid()
    {
        return $this->tableResource->valid();
    }

    public function current()
    {
        return $this->tableResource->current();
    }

    public function key()
    {
        return $this->tableResource->key();
    }

    public function next()
    {
        $this->tableResource->next();
        $this->skipNotMatchingRows();
    }

    public function seek($position)
    {
        $this->tableResource->seek($position);
    }

    public function count()
    {
        return $this->tableResource->count();
    }

    private function skipNotMatchingRows()
    {
        while ($this->valid() && !$this->doesCurrentRowPassesFilters()) {
            $this->next();
        }
    }

    private function doesCurrentRowPassesFilters()
    {
        $row = $this->current();

        $this->executionContext->setCurrentSourceRow($row);

        $result = $this->valueResolver->resolveValue($this->condition, $this->executionContext);

        return (bool)$result;
    }

    public function usesBinaryData()
    {
        $isBinary = false;
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $isBinary = $this->tableResource->usesBinaryData();
        }
        return $isBinary;
    }

    public function convertDataRowToStringRow(array $row)
    {
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $row = $this->tableResource->convertDataRowToStringRow($row);
        }
        return $row;
    }

    public function convertStringRowToDataRow(array $row)
    {
        if ($this->tableResource instanceof UsesBinaryDataInterface) {
            $row = $this->tableResource->convertStringRowToDataRow($row);
        }
        return $row;
    }

}
