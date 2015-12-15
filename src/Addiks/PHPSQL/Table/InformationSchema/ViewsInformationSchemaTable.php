<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Table\InformationSchema;

use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class ViewsInformationSchemaTable extends InformationSchemaTable
{

    ### DATA-PROVIDER-INTERFACE

    public function doesRowExists($rowId = null)
    {
    }

    public function getRowData($rowId = null)
    {
    }

    public function getCellData($rowId, $columnId)
    {
    }

    public function tell()
    {
    }

    ### COUNTABLE

    public function count()
    {
    }

    ### SEEKABLE ITERATOR

    public function seek($position)
    {
    }

    public function rewind()
    {
    }

    public function valid()
    {
    }

    public function current()
    {
    }

    public function key()
    {
    }

    public function next()
    {
    }

}
