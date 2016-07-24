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

namespace Addiks\PHPSQL\Job\Statement;

use Addiks\PHPSQL\Value\Enum\Sql\Show\ShowType;
use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\Executor\ShowExecutor;
use Addiks\PHPSQL\Job\Part\ValuePart;

/**
 *
 */
class ShowStatement extends StatementJob
{

    const EXECUTOR_CLASS = ShowExecutor::class;

    private $type;

    public function setType(ShowType $type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    private $database;

    public function setDatabase($database)
    {
        $this->database = (string)$database;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getResultSpecifier()
    {
    }

    protected $isFull = false;

    public function setIsFull($isFull)
    {
        $this->isFull = (bool)$isFull;
    }

    public function isFull()
    {
        return $this->isFull;
    }

    /**
     * @var ValuePart
     */
    protected $conditionValue;

    public function setConditionValue(ValuePart $conditionValue)
    {
        if (is_null($conditionValue)) {
            $this->conditionValue = null;

        } else {
            $this->conditionValue = $conditionValue;
        }
    }

    public function getConditionValue()
    {
        return $this->conditionValue;
    }

}
