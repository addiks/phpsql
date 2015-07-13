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

namespace Addiks\PHPSQL\SqlParser;

use Addiks\PHPSQL\Entity\Job\Statement\UseStatement;
use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\DatabaseParser;

class UseSqlParser extends SqlParser
{
    
    protected $databaseParser;

    public function getDatabaseParser()
    {
        return $this->databaseParser;
    }

    public function setDatabaseParser(DatabaseParser $databaseParser)
    {
        $this->databaseParser = $databaseParser;
    }

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_USE(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_USE(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_USE());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_USE()) {
            throw new MalformedSql("Tried to parse USE statement when token-iterator does not point to T_USE!", $tokens);
        }
        
        $useJob = new UseStatement();

        if (!$this->databaseParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing database-specifier for USE statement!", $tokens);
        }
        
        $useJob->setDatabase($this->databaseParser->convertSqlToJob($tokens));
        
        return $useJob;
    }
}
