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

use Addiks\PHPSQL\Entity\Job\Part\Value as ValueJob;

use Addiks\PHPSQL\SqlParser\Part\FlowControl\CaseParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser\LikeConditionParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser\EnumConditionParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\Part\FunctionParser;
use Addiks\PHPSQL\SqlParser\Part\ParenthesisParser;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\TokenIterator;

use Addiks\PHPSQL\SQLTokenIterator;

use Addiks\PHPSQL\SqlParser;

class ValueParser extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        
        /* @var $parenthesisParser ParenthesisParser */
        $parenthesisParser = $this->getSqlParserByClassname(ParenthesisParser::class);
        
        /* @var $functionParser FunctionParser */
        $functionParser = $this->getSqlParserByClassname(FunctionParser::class);
        
        /* @var $conditionParser ConditionParser */
        $conditionParser = $this->getSqlParserByClassname(ConditionParser::class);
        
        /* @var $enumConditionParser EnumConditionParser */
        $enumConditionParser = $this->getSqlParserByClassname(EnumConditionParser::class);
        
        /* @var $likeConditionParser LikeConditionParser */
        $likeConditionParser = $this->getSqlParserByClassname(LikeConditionParser::class);
        
        /* @var $columnParser ColumnParser */
        $columnParser = $this->getSqlParserByClassname(ColumnParser::class);
        
        /* @var $caseParser CaseParser */
        $caseParser = $this->getSqlParserByClassname(CaseParser::class);
        
        switch(true){
            
            case is_int($tokens->isTokenNum(SqlToken::T_DEFAULT())):
            case is_int($tokens->isTokenNum(SqlToken::T_NULL())):
            case is_int($tokens->isTokenNum(SqlToken::T_FALSE())):
            case is_int($tokens->isTokenNum(SqlToken::T_TRUE())):
            case is_int($tokens->isTokenNum(SqlToken::T_CURRENT_TIMESTAMP())):
            case is_int($tokens->isTokenNum(SqlToken::T_CURRENT_DATE())):
            case is_int($tokens->isTokenNum(SqlToken::T_CURRENT_TIME())):
            case is_int($tokens->isTokenNum(SqlToken::T_CURRENT_USER())):
            case is_int($tokens->isTokenNum(T_NUM_STRING)):
            case is_int($tokens->isTokenNum(T_CONSTANT_ENCAPSED_STRING)):
            case is_int($tokens->isTokenNum(T_VARIABLE)):
            case $parenthesisParser->canParseTokens($tokens):
            case $functionParser->canParseTokens($tokens):
            case $columnParser->canParseTokens($tokens):
            case $caseParser->canParseTokens($tokens):
                return true;
                
            default:
                return false;
        }
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $valueJob = new ValueJob();
        
        /* @var $enumConditionParser EnumConditionParser */
        $enumConditionParser = $this->getSqlParserByClassname(EnumConditionParser::class);
        
        if (!$this->parsePlainValue($tokens, $valueJob)) {
            throw new MalformedSql("Missing valid value!", $tokens);
        }
        
        do {
            if ($enumConditionParser->canParseTokens($tokens)) {
                $valueJob->addChainValue($enumConditionParser->convertSqlToJob($tokens));
            }
            
        } while ($this->parsePlainOperator($tokens, $valueJob));
        
        return $valueJob;
    }
    
    public function parsePlainOperator(SQLTokenIterator $tokens, ValueJob $valueJob)
    {
        
        /* @var $conditionParser ConditionParser */
        $conditionParser = $this->getSqlParserByClassname(ConditionParser::class);
        
        /* @var $likeConditionParser LikeConditionParser */
        $likeConditionParser = $this->getSqlParserByClassname(LikeConditionParser::class);
        
        switch(true){
            
            case $conditionParser->canParseTokens($tokens):
                $valueJob->addChainValue($conditionParser->convertSqlToJob($tokens));
                break;
            
            case $likeConditionParser->canParseTokens($tokens):
                $valueJob->addChainValue($likeConditionParser->convertSqlToJob($tokens));
                break;
                
            default:
                return false;
        }
        
        return true;
    }
    
    public function parsePlainValue(SQLTokenIterator $tokens, ValueJob $valueJob)
    {
        
        /* @var $parenthesisParser ParenthesisParser */
        $parenthesisParser = $this->getSqlParserByClassname(ParenthesisParser::class);
        
        /* @var $functionParser FunctionParser */
        $functionParser = $this->getSqlParserByClassname(FunctionParser::class);
        
        /* @var $columnParser ColumnParser */
        $columnParser = $this->getSqlParserByClassname(ColumnParser::class);
        
        /* @var $caseParser CaseParser */
        $caseParser = $this->getSqlParserByClassname(CaseParser::class);
        
        switch(true){
            
            case $tokens->seekTokenNum(T_NUM_STRING):
                $valueJob->addChainValue((float)$tokens->getCurrentTokenString());
                break;
        
            case $tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING):
                
                $string = $tokens->getCurrentTokenString();
                
                if (($string[0] === '"' || $string[0] === "'") && $string[0] === $string[strlen($string)-1]) {
                    // remove quotes if needed
                    $string = substr($string, 1, strlen($string)-2);
                }
                
                $valueJob->addChainValue($string);
                break;
        
            case $tokens->seekTokenNum(T_VARIABLE):
                $valueJob->addChainValue(Variable::factory($tokens->getCurrentTokenString()));
                break;
                
            case $parenthesisParser->canParseTokens($tokens):
                $valueJob->addChainValue($parenthesisParser->convertSqlToJob($tokens));
                break;
                
            case $functionParser->canParseTokens($tokens):
                $valueJob->addChainValue($functionParser->convertSqlToJob($tokens));
                break;
                
            case $columnParser->canParseTokens($tokens):
                $valueJob->addChainValue($columnParser->convertSqlToJob($tokens));
                break;
                
            case $caseParser->canParseTokens($tokens):
                $valueJob->addChainValue($caseParser->convertSqlToJob($tokens));
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_DEFAULT()):
            case $tokens->seekTokenNum(SqlToken::T_NULL()):
            case $tokens->seekTokenNum(SqlToken::T_FALSE()):
            case $tokens->seekTokenNum(SqlToken::T_TRUE()):
            case $tokens->seekTokenNum(SqlToken::T_CURRENT_TIMESTAMP()):
            case $tokens->seekTokenNum(SqlToken::T_CURRENT_DATE()):
            case $tokens->seekTokenNum(SqlToken::T_CURRENT_TIME()):
            case $tokens->seekTokenNum(SqlToken::T_CURRENT_USER()):
                $valueJob->addChainValue($tokens->getCurrentTokenNumber());
                break;
                
            default:
                return false;
        }
        
        return true;
    }
}
