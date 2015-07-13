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

use Addiks\PHPSQL\Entity\Job\Part\ConditionJob;
use Addiks\PHPSQL\Value\Enum\Sql\Operator;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\Part;
use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\SQLTokenIterator;

class ConditionParser extends Part
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

    public function canParseTokens(SQLTokenIterator $tokens, &$checkFlags = 0)
    {
        
        $previousIndex = $tokens->getIndex();
        
        $operator = $this->parseCondition($tokens);
        
        $tokens->seekIndex($previousIndex);
        return !is_null($operator);
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $condition = $this->parseCondition($tokens);
        if (is_null($condition)) {
            throw new MalformedSql("Missing valid operator in condition!", $tokens);
        }

        $conditionJob = new ConditionJob();
        $conditionJob->setOperator($condition);
        
        if ($this->valueParser->canParseTokens($tokens)) {
            $conditionJob->setLastParameter($this->valueParser->convertSqlToJob($tokens));
            
        }
        
        return $conditionJob;
    }
    
    /**
     * @param SQLTokenIterator $tokens
     * @return Operator
     */
    protected function parseCondition(SQLTokenIterator $tokens)
    {
        
        switch(true){
            
            case $tokens->seekTokenText('='):
                return Operator::OP_EQUAL();
            
            case $tokens->seekTokenText('<=>'):
                return Operator::OP_EQUAL_NULLSAFE();
            
            case $tokens->seekTokenText('!='):
                return Operator::OP_NOT_EQUAL();
                
            case $tokens->seekTokenText('<>'):
                return Operator::OP_LESSERGREATER();
                
            case $tokens->seekTokenText('<='):
                return Operator::OP_LESSEREQUAL();
                
            case $tokens->seekTokenText('<'):
                return Operator::OP_LESSER();
            
            case $tokens->seekTokenText('>='):
                return Operator::OP_GREATEREQUAL();
                
            case $tokens->seekTokenText('>'):
                return Operator::OP_GREATER();
                
            case $tokens->seekTokenText('+'):
                return Operator::OP_ADDITION();
                
            case $tokens->seekTokenText('-'):
                return Operator::OP_SUBTRACTION();
                
            case $tokens->seekTokenText('*'):
                return Operator::OP_MULTIPLICATION();
                
            case $tokens->seekTokenText('/'):
                return Operator::OP_DIVISION();
                                
            case $tokens->seekTokenNum(SqlToken::T_IS()):
                if ($tokens->seekTokenNum(SqlToken::T_NOT())) {
                    return Operator::OP_IS_NOT();
                } else {
                    return Operator::OP_IS();
                }
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_AND()):
                return Operator::OP_AND();
                
            case $tokens->seekTokenNum(SqlToken::T_OR()):
                return Operator::OP_OR();
            
            case $tokens->seekTokenNum(SqlToken::T_BETWEEN(), TokenIterator::NEXT, SqlToken::T_NOT()):
                if ($tokens->isTokenNum(SqlToken::T_NOT(), TokenIterator::PREVIOUS)) {
                    return Operator::OP_NOT_BETWEEN();
                } else {
                    return Operator::OP_BETWEEN();
                }
                
            default:
                return null;
        }
    }
}
