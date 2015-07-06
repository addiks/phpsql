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

namespace Addiks\PHPSQL\Entity\Job\Statement\Create;

use Addiks\PHPSQL\Entity\Job\Statement\CreateStatement;

use Addiks\PHPSQL\Entity\Job\Statement\Create;
use Addiks\PHPSQL\Executor\CreateDatabaseExecutor;

/**
 *
 */
class CreateDatabaseStatement extends CreateStatement
{
    
    const EXECUTOR_CLASS = CreateDatabaseExecutor::class;

    private $characterSet;
    
    public function setCharacterSet($characterSet)
    {
        $this->characterSet = $characterSet;
    }
    
    public function getCharacterSet()
    {
        return $this->characterSet;
    }
    
    private $collation;
    
    public function setCollation($collation)
    {
        $this->collation = $collation;
    }
    
    public function getCollation()
    {
        return $this->collation;
    }
}
