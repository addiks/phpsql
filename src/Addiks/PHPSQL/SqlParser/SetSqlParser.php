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

use Addiks\PHPSQL\Job\Statement\SetStatement;
use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\SqlParser;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;

class SetSqlParser extends SqlParser
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
        return is_int($tokens->isTokenNum(SqlToken::T_SET(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_SET(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_SET());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_SET()) {
            throw new ErrorException("Tried to parse SET statement when token-iterator is not at T_SET!");
        }
        
        $setJob = new SetStatement();
        
        if (!$tokens->seekTokenNum(T_STRING)) {
            throw new MalformedSqlException("Missing configuration name for SET statement!", $tokens);
        }
        
        $setJob->setKey($tokens->getCurrentTokenString());
        
        $tokens->seekTokenText('=');
        
        if (!$this->valueParser->canParseTokens($tokens)) {
            throw new MalformedSqlException("Missing valid value definition for SET statement!", $tokens);
        }
        
        $setJob->setValue($this->valueParser->convertSqlToJob($tokens));
        
        return $setJob;
    }
}
