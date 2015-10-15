<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Job\Part;

use Addiks\PHPSQL\Job\Part\ValuePart;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

class GroupingDefinition
{
    public function __construct(
        ValuePart $value = null,
        SqlToken $direction = null,
        $withRollup = false
    ) {
        $this->value = $value;
        $this->direction = (SqlToken::T_ASC() ?$direction :SqlToken::T_DESC());
        $this->withRollup = (bool)$withRollup;
    }

    protected $value;

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    protected $direction;

    public function setDirection()
    {
        $this->direction = (SqlToken::T_ASC() ?$direction :SqlToken::T_DESC());
    }

    public function getDirection()
    {
        return $this->direction;
    }

    protected $withRollup = false;

    public function setWithRollup($withRollup)
    {
        $this->withRollup = (bool)$withRollup;
    }

    public function isWithRollup()
    {
        return $this->withRollup;
    }
}
