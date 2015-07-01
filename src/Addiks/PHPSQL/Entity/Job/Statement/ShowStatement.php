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

use Addiks\PHPSQL\Value\Enum\Sql\Show\ShowType;

use Addiks\PHPSQL\Entity\Job\Statement;
use Addiks\PHPSQL\Service\Executor\ShowExecutor;

/**
 *
 * @Addiks\Statement(executorClass="ShowExecutor")
 * @author gerrit
 *
 */
class ShowStatement extends Statement
{

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
}
