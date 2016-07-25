<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Iterators;

use Iterator;
use Addiks\PHPSQL\Iterators\DataProviderInterface;
use Addiks\PHPSQL\Iterators\UsesBinaryDataInterface;
use IteratorAggregate;

class AliasedResourceIterator implements Iterator, DataProviderInterface, UsesBinaryDataInterface
{

    public function __construct(
        DataProviderInterface $dataProvider,
        $alias
    ) {
        $this->dataProvider = $dataProvider;
        $this->alias = $alias;
    }

    /**
     * @var DataProviderInterface
     */
    protected $dataProvider;

    /**
     * @var string
     */
    protected $alias;

    /**
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->getTableSchema();
    }

    public function doesRowExists($rowId = null)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->doesRowExists($rowId);
    }

    public function getRowData($rowId = null)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->getRowData($rowId);
    }

    public function getCellData($rowId, $columnId)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->getCellData($rowId, $columnId);
    }

    public function tell()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->tell();
    }

    public function seek($rowId)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->seek($rowId);
    }

    public function count()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        return $dataProvider->count();
    }

    public function usesBinaryData()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        /* @var $usesBinaryData boolean */
        $usesBinaryData = false;

        if ($dataProvider instanceof UsesBinaryDataInterface) {
            $usesBinaryData = $dataProvider->usesBinaryData();
        }

        return $usesBinaryData;
    }

    public function convertDataRowToStringRow(array $row)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        /* @var $convertedRow array */
        $convertedRow = $row;

        #if ($dataProvider instanceof UsesBinaryDataInterface && $dataProvider->usesBinaryData()) {
        #    $convertedRow = $dataProvider->convertDataRowToStringRow($row);
        #}

        return $convertedRow;
    }

    public function convertStringRowToDataRow(array $row)
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        /* @var $convertedRow array */
        $convertedRow = $row;

        if ($dataProvider instanceof UsesBinaryDataInterface && $dataProvider->usesBinaryData()) {
            $convertedRow = $dataProvider->convertStringRowToDataRow($row);
        }

        return $convertedRow;
    }

    public function rewind()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        if ($dataProvider instanceof IteratorAggregate) {
            $dataProvider = $dataProvider->getIterator();
        }

        if ($dataProvider instanceof Iterator) {
            $dataProvider->rewind();
        }
    }

    public function valid()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        if ($dataProvider instanceof IteratorAggregate) {
            $dataProvider = $dataProvider->getIterator();
        }

        if ($dataProvider instanceof Iterator) {
            return $dataProvider->valid();
        }
    }

    public function key()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        if ($dataProvider instanceof IteratorAggregate) {
            $dataProvider = $dataProvider->getIterator();
        }

        if ($dataProvider instanceof Iterator) {
            return $dataProvider->key();
        }
    }

    public function current()
    {
        /* @var $row array */
        $row = null;

        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        /* @var $alias string */
        $alias = $this->alias;

        /* @var $iterator Iterator */
        $iterator = $dataProvider;

        if ($iterator instanceof IteratorAggregate) {
            $iterator = $iterator->getIterator();
        }

        if ($dataProvider instanceof Iterator) {
            $row = $dataProvider->current();

            if ($dataProvider instanceof UsesBinaryDataInterface && $dataProvider->usesBinaryData()) {
                $row = $dataProvider->convertDataRowToStringRow($row);
            }

            /* @var $newRow array */
            $newRow = $row;

            foreach ($row as $key => $value) {
                $newRow["{$alias}.{$key}"] = $value;
            }

            $row = $newRow;
        }

        return $row;
    }

    public function next()
    {
        /* @var $dataProvider DataProviderInterface */
        $dataProvider = $this->dataProvider;

        if ($dataProvider instanceof IteratorAggregate) {
            $dataProvider = $dataProvider->getIterator();
        }

        if ($dataProvider instanceof Iterator) {
            $dataProvider->next();
        }
    }
}
