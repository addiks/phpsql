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

use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\Entity\Job\Statement\UseStatement;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;

class UseSqlParser extends SqlParser
{
    
    protected $valueParser;

    public function getValueParser()
    {
        return $this->valueParser;
    }

    public function setValueParser(ValueParser $valueParser)
    {
        $this->valueParser = $valueParser;
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
        

        if (!$this->valueParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing database-specifier for USE statement!", $tokens);
        }
        
        $useJob = new UseStatement();
        $useJob->setDatabase($this->valueParser->convertSqlToJob($tokens));
        
        return $useJob;
    }
}
