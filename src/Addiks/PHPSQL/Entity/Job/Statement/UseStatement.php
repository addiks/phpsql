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
use Addiks\PHPSQL\Executor\UseExecutor;
use Addiks\PHPSQL\Entity\Job\Part\ValuePart;

/**
 *
 */
class UseStatement extends StatementJob
{
    
    const EXECUTOR_CLASS = UseExecutor::class;

    private $database;
    
    public function setDatabase(ValuePart $database)
    {
        $this->database = $database;
    }
    
    public function getDatabase()
    {
        return $this->database;
    }

    public function getResultSpecifier()
    {
    }
}
