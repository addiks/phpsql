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

namespace Addiks\PHPSQL\Job;

use Addiks\PHPSQL\SqlParser\SqlParser;

/**
 * A job holds data extracted from an sql-statement.
 * Jobs get created in the SqlParser from an SQL-Statement
 * and get executed in a StatementExecuter.
 * A job can have sub-jobs (e.g.: column-definition-jobs inside a create-table-job).
 *
 * @see StatementExecuter
 * @see SqlParser
 */
abstract class Job
{
    
    public function getExclusiveTableLocks()
    {
        return array();
    }
    
    public function getSharedTableLocks()
    {
        return array();
    }
    
    public function checkPlausibility()
    {
    }

    public function resolve()
    {
    }

}
