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

namespace Addiks\PHPSQL\SqlParser\Part;

use Addiks\PHPSQL\Entity\Job\Part\Parenthesis as ParenthesisPart;

use Addiks\PHPSQL\Entity\Job\Part\Join;

use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;

use Addiks\PHPSQL\SqlParser\SelectSqlParser;

use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;

use Addiks\PHPSQL\SqlParser\Part;

use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\TokenIterator;

use Addiks\PHPSQL\SQLTokenIterator;

class JoinDefinition extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        $previousIndex = $tokens->getIndex();
        
        /* @var $tableParser TableParser */
        $this->factorize($tableParser);
        
        /* @var $selectParser SelectSqlParser */
        $this->factorize($selectParser);
        
        if ($tokens->seekTokenText('(')) {
            $return = $selectParser->canParseTokens($tokens);
            
        } else {
            $return = $tableParser->canParseTokens($tokens);
        }
        
        $tokens->seekIndex($previousIndex);
        return $return;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $columnParser ColumnParser */
        $this->factorize($columnParser);
        
        /* @var $joinJob Join */
        $this->factorize($joinJob);
        
        /* @var $tableJoin \Addiks\PHPSQL\Entity\Job\Part\Join\Table */
        $this->factorize($tableJoin);
        
        $tableJoin->setDataSource($this->parseTableSource($tokens));
        
        $joinJob->addTable(clone $tableJoin);
        
        while (!is_null($joinData = $this->parseJoinOperator($tokens))) {
            $tableJoin->setDataSource($this->parseTableSource($tokens));
            $tableJoin->setIsInner((bool)$joinData['isInner']);
            $tableJoin->setIsRight((bool)$joinData['isRight']);
            
            if ($tokens->seekTokenNum(SqlToken::T_ON())) {
                if (!$valueParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid condition after ON for JOIN!", $tokens);
                }
                $tableJoin->setCondition($valueParser->convertSqlToJob($tokens));
                
            } elseif ($tokens->seekTokenNum(SqlToken::T_USING())) {
                if ($tokens->seekTokenText('(')) {
                    throw new MalformedSql("Missing begin parenthesis after USING for JOIN!", $tokens);
                }
                if (!$columnParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid column specifier after USING for JOIN!", $tokens);
                }
                $tableJoin->setUsingColumnCondition($columnParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText(')')) {
                    throw new MalformedSql("Missing ending parenthesis after USING for JOIN!", $tokens);
                }
            }
            
            $joinJob->addTable(clone $tableJoin);
        }
        
        return $joinJob;
    }
    
    protected function parseJoinOperator(SQLTokenIterator $tokens)
    {
        
        $joinData = array(
            'isInner' => false,
            'isRight' => false,
        );
        
        if ($tokens->seekTokenText(',')) {
            return $joinData;
        }
        
        if (!$tokens->isTokenNum(SqlToken::T_JOIN(), TokenIterator::NEXT, [SqlToken::T_LEFT(), SqlToken::T_RIGHT(), SqlToken::T_INNER(), SqlToken::T_OUTER(), SqlToken::T_CROSS()])) {
            return null;
        }
        
        while (true) {
            switch(true){
                    
                case $tokens->seekTokenNum(SqlToken::T_RIGHT()):
                    $joinData['isRight'] = true;
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_LEFT()):
                    $joinData['isRight'] = false;
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_INNER()):
                    $joinData['isInner'] = true;
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CROSS()):
                case $tokens->seekTokenNum(SqlToken::T_OUTER()):
                    $joinData['isInner'] = false;
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_JOIN()):
                    return $joinData;
                    
                default:
                    throw new MalformedSql("Invalid JOIN definition!");
            }
        }
    }
    
    protected function parseTableSource(SQLTokenIterator $tokens)
    {
        
        /* @var $tableParser TableParser */
        $this->factorize($tableParser);
        
        /* @var $parenthesisParser Parenthesis */
        $this->factorize($parenthesisParser);
        
        /* @var $parenthesis ParenthesisPart */
        $this->factorize($parenthesis);
        
        switch(true){
            
            case $tableParser->canParseTokens($tokens):
                $parenthesis->setContain($tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
                    $parenthesis->setAlias($tokens->getCurrentTokenString());
                }
                return $parenthesis;
                
            case $parenthesisParser->canParseTokens($tokens):
                $parenthesisJob = $parenthesisParser->convertSqlToJob($tokens);
                
                if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
                    $parenthesis->setAlias($tokens->getCurrentTokenString());
                }
                
                // resolve cascaded parenthesis
                $extractParenthesis = $parenthesisJob;
                while ($extractParenthesis->getContain() instanceof Parenthesis) {
                    $extractParenthesis = $extractParenthesis->getContain();
                }
                
                if ($extractParenthesis->getContain() instanceof Select) {
                    return $parenthesisJob; // return original parenthesis for correct alias
                    
                } else {
                    throw new MalformedSql("Parenthesis in JOIN condition has to contain SELECT statement!", $tokens);
                }
            
            default:
                throw new MalformedSql("Missing valid table-source in JOIN defintion!", $tokens);
        }
    }
}
