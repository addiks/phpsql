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

namespace Addiks\PHPSQL\Service\SqlParser\Part\Condition;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

class Like extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens, &$skipChecks = 0)
    {
        $previousIndex = $tokens->getIndex();
        
        $result = is_int($tokens->isTokenNum(SqlToken::T_LIKE(), TokenIterator::NEXT, [SqlToken::T_NOT()]))
               || is_int($tokens->isTokenNum(SqlToken::T_LIKE(), TokenIterator::CURRENT, [SqlToken::T_NOT()]));
        
        $tokens->seekIndex($previousIndex);
        return $result;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens, &$skipChecks = 0)
    {
        
        /* @var $likeCondition Like */
        $this->factorize($likeCondition);
        
        $likeCondition->setIsNegated($tokens->seekTokenNum(SqlToken::T_NOT()));
        
        if (!$tokens->seekTokenNum(SqlToken::T_LIKE())) {
            throw new ErrorException("Missing [NOT] LIKE after value when parsing LIKE condition! (not used 'canParseTokens'?)");
        }
        
        return $likeCondition;
    }
}
