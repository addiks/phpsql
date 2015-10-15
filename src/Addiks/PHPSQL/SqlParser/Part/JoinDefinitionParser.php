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

use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Part;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Job\Part\Join\TableJoin;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\Part\ParenthesisParser;
use Addiks\PHPSQL\Job\Part\ParenthesisPart;
use Addiks\PHPSQL\Job\Part\Join;
use Addiks\PHPSQL\Job\Statement\SelectStatement;

class JoinDefinitionParser extends Part
{
    
    protected $tableParser;

    public function getTableParser()
    {
        return $this->tableParser;
    }

    public function setTableParser(TableParser $tableParser)
    {
        $this->tableParser = $tableParser;
    }

    protected $valueParser;

    public function getValueParser()
    {
        return $this->valueParser;
    }

    public function setValueParser(ValueParser $valueParser)
    {
        $this->valueParser = $valueParser;
    }

    protected $selectParser;

    public function getSelectParser()
    {
        return $this->selectParser;
    }

    public function setSelectParser(SelectSqlParser $selectParser)
    {
        $this->selectParser = $selectParser;
    }

    protected $columnParser;

    public function getColumnParser()
    {
        return $this->columnParser;
    }

    public function setColumnParser(ColumnParser $columnParser)
    {
        $this->columnParser = $columnParser;
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
        $previousIndex = $tokens->getIndex();
        
        if ($tokens->seekTokenText('(')) {
            $return = $this->selectParser->canParseTokens($tokens);
            
        } else {
            $return = $this->tableParser->canParseTokens($tokens);
        }
        
        $tokens->seekIndex($previousIndex);
        return $return;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tableJoin = new TableJoin();
        $tableJoin->setDataSource($this->parseTableSource($tokens));
        
        $joinJob = new Join();
        $joinJob->addTable(clone $tableJoin);
        
        while (!is_null($joinData = $this->parseJoinOperator($tokens))) {
            $tableJoin->setDataSource($this->parseTableSource($tokens));
            $tableJoin->setIsInner((bool)$joinData['isInner']);
            $tableJoin->setIsRight((bool)$joinData['isRight']);
            
            if ($tokens->seekTokenNum(SqlToken::T_ON())) {
                if (!$this->valueParser->canParseTokens($tokens)) {
                    throw new MalformedSqlException("Missing valid condition after ON for JOIN!", $tokens);
                }
                $tableJoin->setCondition($this->valueParser->convertSqlToJob($tokens));
                
            } elseif ($tokens->seekTokenNum(SqlToken::T_USING())) {
                if ($tokens->seekTokenText('(')) {
                    throw new MalformedSqlException("Missing begin parenthesis after USING for JOIN!", $tokens);
                }
                if (!$this->columnParser->canParseTokens($tokens)) {
                    throw new MalformedSqlException("Missing valid column specifier after USING for JOIN!", $tokens);
                }
                $tableJoin->setUsingColumnCondition($this->columnParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText(')')) {
                    throw new MalformedSqlException("Missing ending parenthesis after USING for JOIN!", $tokens);
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
        
        if (!$tokens->isTokenNum(
            SqlToken::T_JOIN(),
            TokenIterator::NEXT,
            [
                    SqlToken::T_LEFT(),
                    SqlToken::T_RIGHT(),
                    SqlToken::T_INNER(),
                    SqlToken::T_OUTER(),
                    SqlToken::T_CROSS()
                ]
        )) {
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
                    throw new MalformedSqlException("Invalid JOIN definition!");
            }
        }
    }
    
    protected function parseTableSource(SQLTokenIterator $tokens)
    {
        
        $parenthesis = new ParenthesisPart();
        
        switch(true){
            
            case $this->tableParser->canParseTokens($tokens):
                $parenthesis->setContain($this->tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
                    $parenthesis->setAlias($tokens->getCurrentTokenString());
                }
                break;
                
            case $this->parenthesisParser->canParseTokens($tokens):
                $parenthesisJob = $this->parenthesisParser->convertSqlToJob($tokens);
                
                if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
                    $parenthesis->setAlias($tokens->getCurrentTokenString());
                }
                
                // resolve cascaded parenthesis
                $extractParenthesis = $parenthesisJob;
                while ($extractParenthesis->getContain() instanceof Parenthesis) {
                    $extractParenthesis = $extractParenthesis->getContain();
                }
                
                if ($extractParenthesis->getContain() instanceof SelectStatement) {
                    $parenthesis = $parenthesisJob; // return original parenthesis for correct alias
                    
                } else {
                    throw new MalformedSqlException("Parenthesis in JOIN condition has to contain SELECT statement!", $tokens);
                }
                break;
            
            default:
                throw new MalformedSqlException("Missing valid table-source in JOIN defintion!", $tokens);
        }

        return $parenthesis;
    }
}
