<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\ValueResolver\FunctionResolver;

use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;
use Addiks\PHPSQL\Entity\ExecutionContext;
use Addiks\PHPSQL\Schema\SchemaManager;

class DatabaseFunction
{
    public function __construct(ValueResolver $valueResolver)
    {
    }

    public function executeFunction(
        FunctionJob $function,
        ExecutionContext $context
    ) {
        /* @var $schemaManager SchemaManager */
        $schemaManager = $context->getSchemaManager();

        $databaseId = $schemaManager->getCurrentlyUsedDatabaseId();

        return $databaseId;
    }
}
