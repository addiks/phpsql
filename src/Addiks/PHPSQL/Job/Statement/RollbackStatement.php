<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Job\Statement;

use Addiks\PHPSQL\Job\StatementJob;
use Addiks\PHPSQL\StatementExecutor\TransactionExecutor;

class RollbackStatement extends StatementJob
{

    const EXECUTOR_CLASS = TransactionExecutor::class;

}
