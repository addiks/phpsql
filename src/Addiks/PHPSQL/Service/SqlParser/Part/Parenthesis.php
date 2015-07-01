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

namespace Addiks\PHPSQL\Service\SqlParser\Part;

use Addiks\PHPSQL\Service\SqlParser\Part;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\Analyser\Tool\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

class Parenthesis extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens, $from = TokenIterator::NEXT)
    {
        return is_int($tokens->isTokenText('(', $from));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens, $from = TokenIterator::NEXT)
    {
        
        if (!$tokens->seekTokenText('(', $from)) {
            throw new Error("Tried to parse sql-parenthesis when token-iterator does not point to paranthesis ('(' sign)!");
        }
        
        /* @var $subQueryParser Select */
        $this->factorize($subQueryParser);
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $parenthesis Parenthesis */
        $this->factorize($parenthesis);
        
        switch(true){
            
            case $subQueryParser->canParseTokens($tokens):
                $parenthesis->setContain($subQueryParser->convertSqlToJob($tokens));
                break;
                
            case $valueParser->canParseTokens($tokens):
                $parenthesis->setContain($valueParser->convertSqlToJob($tokens));
                break;
        }
        
        if (!$tokens->seekTokenText(')')) {
            throw new MalformedSql("Missing ')' at the end of a parenthesis!", $tokens);
        }
        
        if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
            $parenthesis->setAlias($tokens->getCurrentTokenString());
        }
        
        if ($parenthesis->getContain() instanceof Select && $tokens->isTokenNum(SqlToken::T_UNION())) {
            /* @var $unionSelect Select */
            $this->factorize($unionSelect);
            
            while ($tokens->seekTokenNum(SqlToken::T_UNION())) {
                /* @var $lastUnionedSelect Select */
                $lastUnionedSelect = $parenthesis->getContain();
                while (!is_null($lastUnionedSelect->getUnionSelect())) {
                    $lastUnionedSelect = $lastUnionedSelect->getUnionSelect();
                }
                
                $isUnionAll      = $tokens->seekTokenNum(SqlToken::T_ALL());
                $isUnionDistinct = $tokens->seekTokenNum(SqlToken::T_DISTINCT());
                $isUnionAll      = $isUnionAll || $tokens->seekTokenNum(SqlToken::T_ALL());
                
                if ($isUnionAll && $isUnionDistinct) {
                    throw new MalformedSql("UNION cannot be ALL and DISTINCT at the same time!", $tokens);
                }
                    
                $isUnionInParenthesis = $tokens->seekTokenText('(');
                    
                if (!$subQueryParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing following SELECT statement after UNION in SELECT statement!", $tokens);
                }
                $lastUnionedSelect->setUnionSelect($subQueryParser->convertSqlToJob($tokens), $isUnionDistinct);
                    
                if ($isUnionInParenthesis && !$tokens->seekTokenText(')')) {
                    throw new MalformedSql("Missing ending parenthesis after UNION in SELECT statement!", $tokens);
                }
            }
            
            $unionSelect->setUnionSelect($parenthesis->getContain());
            
            ### APPENDED CONDITION (HAVING)
            
            if ($tokens->seekTokenNum(SqlToken::T_HAVING())) {
                if (!$valueParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing condition for WHERE clause in SELECT statement!", $tokens);
                }
            
                /* @var $condition Condition */
                $this->factorize($condition);
            
                $condition->setFirstParameter($valueParser->convertSqlToJob($tokens));
            
                $unionSelect->setResultFilter($condition);
            }
            
            ### ORDER
            
            if ($tokens->seekTokenNum(SqlToken::T_ORDER())) {
                if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                    throw new MalformedSql("Missing BY after ORDER on SELECT statement!", $tokens);
                }
                do {
                    if (!$valueParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing value for ORDER BY part on SELECT statement!", $tokens);
                    }
                
                    $orderValue = $valueParser->convertSqlToJob($tokens);
                    if ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                        $unionSelect->addOrderColumn($orderValue, SqlToken::T_DESC());
                    
                    } else {
                        $tokens->seekTokenNum(SqlToken::T_ASC());
                        $unionSelect->addOrderColumn($orderValue, SqlToken::T_ASC());
                    }
                
                } while ($tokens->seekTokenText(','));
            }
            
            ### LIMIT
            
            if ($tokens->seekTokenNum(SqlToken::T_LIMIT())) {
                if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                    throw new MalformedSql("Missing offset number for LIMIT part in SELECT statement!", $tokens);
                }
                $unionSelect->setLimitOffset((int)$tokens->getCurrentTokenString());
                if ($tokens->seekTokenText(',')) {
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing length number for LIMIT part in SELECT statement!", $tokens);
                    }
                    $unionSelect->setLimitRowCount((int)$tokens->getCurrentTokenString());
                }
            }
            
            $parenthesis->setContain($unionSelect);
        }
        
        return $parenthesis;
    }
}
