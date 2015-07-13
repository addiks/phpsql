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

use Addiks\PHPSQL\Entity\Job\Part\ValuePart as ValueJob;
use Addiks\PHPSQL\SqlParser\Part\FlowControl\CaseParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\LikeConditionParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\EnumConditionParser;
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
    
    protected $conditionParser;

    public function getConditionParser()
    {
        return $conditionParser;
    }

    public function setConditionParser(ConditionParser $conditionParser)
    {
        $this->conditionParser = $conditionParser;
    }

    protected $functionParser;

    public function getFunctionParser()
    {
        return $functionParser;
    }

    public function setFunctionParser(FunctionParser $functionParser)
    {
        $this->functionParser = $functionParser;
    }

    protected $columnParser;

    public function getColumnParser()
    {
        return $columnParser;
    }

    public function setColumnParser(ColumnParser $columnParser)
    {
        $this->columnParser = $columnParser;
    }

    protected $enumConditionParser;

    public function getEnumConditionParser()
    {
        return $enumConditionParser;
    }

    public function setEnumConditionParser(EnumConditionParser $enumConditionParser)
    {
        $this->enumConditionParser = $enumConditionParser;
    }

    protected $likeConditionParser;

    public function getLikeConditionParser()
    {
        return $likeConditionParser;
    }

    public function setLikeConditionParser(LikeConditionParser $likeConditionParser)
    {
        $this->likeConditionParser = $likeConditionParser;
    }
    
    protected $caseParser;

    public function getCaseParser()
    {
        return $caseParser;
    }

    public function setCaseParser(CaseParser $caseParser)
    {
        $this->caseParser = $caseParser;
    }

    protected $parenthesisParser;

    public function getParenthesisParser()
    {
        return $this->parenthesisParser;
    }
    
    public function setParenthesisParser(ParenthesisParser $parenthesisParser)
    {
        $this->parenthesisParser = $parenthesisParser;
    }


    public function canParseTokens(SQLTokenIterator $tokens)
    {
        
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
            case $this->parenthesisParser->canParseTokens($tokens):
            case $this->functionParser->canParseTokens($tokens):
            case $this->columnParser->canParseTokens($tokens):
            case $this->caseParser->canParseTokens($tokens):
                return true;
                
            default:
                return false;
        }
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $valueJob = new ValueJob();
        
        if (!$this->parsePlainValue($tokens, $valueJob)) {
            throw new MalformedSql("Missing valid value!", $tokens);
        }
        
        do {
            if ($this->enumConditionParser->canParseTokens($tokens)) {
                $valueJob->addChainValue($this->enumConditionParser->convertSqlToJob($tokens));
            }
            
        } while ($this->parsePlainOperator($tokens, $valueJob));
        
        return $valueJob;
    }
    
    public function parsePlainOperator(SQLTokenIterator $tokens, ValueJob $valueJob)
    {

        switch(true){
            
            case $this->conditionParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->conditionParser->convertSqlToJob($tokens));
                break;
            
            case $this->likeConditionParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->likeConditionParser->convertSqlToJob($tokens));
                break;
                
            default:
                return false;
        }
        
        return true;
    }
    
    public function parsePlainValue(SQLTokenIterator $tokens, ValueJob $valueJob)
    {
        
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
                
            case $this->parenthesisParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->parenthesisParser->convertSqlToJob($tokens));
                break;
                
            case $this->functionParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->functionParser->convertSqlToJob($tokens));
                break;
                
            case $this->columnParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->columnParser->convertSqlToJob($tokens));
                break;
                
            case $this->caseParser->canParseTokens($tokens):
                $valueJob->addChainValue($this->caseParser->convertSqlToJob($tokens));
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
