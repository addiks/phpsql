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

use Addiks\PHPSQL\Service\SqlParser\Part;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

class Enum extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens, &$checkFlags = 0)
    {
        $previousIndex = $tokens->getIndex();
        
        if (!$tokens->seekTokenNum(SqlToken::T_IN(), TokenIterator::NEXT, [SqlToken::T_NOT()])) {
            $tokens->seekIndex($previousIndex);
            return false;
        }
        
        $result = is_int($tokens->isTokenText('('));
        
        $tokens->seekIndex($previousIndex);
        return $result;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens, &$checkFlags = 0)
    {
        
        if (!$tokens->seekTokenNum(SqlToken::T_IN(), TokenIterator::NEXT, [SqlToken::T_NOT()])) {
            throw new ErrorException("Missing IN after string when tried to parse IN-condition! (was 'canParseTokens' not used?)");
        }
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $enumConditionJob Enum */
        $this->factorize($enumConditionJob);
        
        $enumConditionJob->setIsNegated($tokens->isTokenNum(SqlToken::T_NOT(), TokenIterator::PREVIOUS));
        
        if (!$tokens->seekTokenText('(')) {
            throw new MalformedSql("Missing beginning parenthesis for IN condition!", $tokens);
        }
        
        do {
            if (!$valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid value in value-listing for IN condition!", $tokens);
            }
            $enumConditionJob->addValue($valueParser->convertSqlToJob($tokens));
        } while ($tokens->seekTokenText(','));
        
        if (!$tokens->seekTokenText(')')) {
            throw new MalformedSql("Missing ending parenthesis for IN condition!", $tokens);
        }
        
        return $enumConditionJob;
    }
}
