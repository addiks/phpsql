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

namespace Addiks\PHPSQL\SqlParser\Part\FlowControl;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\TokenIterator;

use Addiks\PHPSQL\SQLTokenIterator;

use Addiks\PHPSQL\SqlParser;

class CaseParser extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_CASE(), TokenIterator::NEXT))
            || is_int($tokens->isTokenNum(SqlToken::T_CASE(), TokenIterator::CURRENT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        $tokens->seekTokenNum(SqlToken::T_CASE());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_CASE()) {
            throw new ErrorException("Tried to parse CASE statement when token-iterator is not at CASE!");
        }
        
        /* @var $caseJob Case */
        $this->factorize($caseJob);
        
        if (!$tokens->isTokenNum(SqlToken::T_WHEN()) && $valueParser->canParseTokens($tokens)) {
            $caseJob->setCaseValue($valueParser->convertSqlToJob($tokens));
        }
        
        do {
            if (!$tokens->seekTokenNum(SqlToken::T_WHEN())) {
                throw new MalformedSql("Missing WHEN in CASE statement!", $tokens);
            }
            if (!$valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid when-value in CASE statement!", $tokens);
            }
            $whenValue = $valueParser->convertSqlToJob($tokens);
            
            if (!$tokens->seekTokenNum(SqlToken::T_THEN())) {
                throw new MalformedSql("Missing THEN in CASE statement!", $tokens);
            }
            switch(true){
                case $valueParser->canParseTokens($tokens):
                    $thenExpression = $valueParser->convertSqlToJob($tokens);
                    break;
                
                default:
                    throw new MalformedSql("Missing valid THEN statement for CASE statement!", $tokens);
            }
            $caseJob->addWhenThenStatement($whenValue, $thenExpression);
            
        } while ($tokens->isTokenNum(SqlToken::T_WHEN()));
        
        if ($tokens->seekTokenNum(SqlToken::T_ELSE())) {
            switch(true){
                case $valueParser->canParseTokens($tokens):
                    $caseJob->setElseStatement($valueParser->convertSqlToJob($tokens));
                    break;
                
                default:
                    throw new MalformedSql("Missing valid THEN statement for CASE statement!", $tokens);
            }
        }
        
        if (!$tokens->seekTokenNum(SqlToken::T_END())) {
            throw new MalformedSql("Missing END at the end of for CASE statement!", $tokens);
        }
        
        $tokens->seekTokenNum(SqlToken::T_CASE());
        return $caseJob;
    }
}
