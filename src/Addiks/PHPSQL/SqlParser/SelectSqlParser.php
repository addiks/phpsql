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

use Addiks\PHPSQL\Entity\Job\Part\ConditionJob;
use Addiks\PHPSQL\SqlParser\Part\FunctionParser;
use Addiks\PHPSQL\SqlParser\Part\Condition;
use Addiks\PHPSQL\Value\Enum\Sql\Select\SpecialFlags;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\Part\JoinDefinition;
use Addiks\Analyser\Service\TokenParser\Expression\FunctionCallParser;
use Addiks\PHPSQL\SqlParser\Part\Parenthesis;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\TokenIterator;

use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\Part\ParenthesisParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\SqlParser\Part\JoinDefinitionParser;
use Addiks\PHPSQL\Entity\Job\Part\GroupingDefinition;

/**
 * This class converts a tokenized sql-select-statement into an job-entity.
 * @see SQLTokenIterator
 * @see Select
 */
class SelectSqlParser extends SqlParser
{
    
    protected $parenthesisParser;

    public function getParenthesisParser()
    {
        return $this->parenthesisParser;
    }

    public function setParenthesisParser(ParenthesisParser $parenthesisParser)
    {
        $this->parenthesisParser = $parenthesisParser;
    }

    protected $functionParser;

    public function getFunctionParser()
    {
        return $this->functionParser;
    }

    public function setFunctionParser(FunctionParser $functionParser)
    {
        $this->functionParser = $functionParser;
    }

    protected $joinParser;

    public function getJoinParser()
    {
        return $this->joinParser;
    }

    public function setJoinParser(JoinDefinitionParser $joinParser)
    {
        $this->joinParser = $joinParser;
    }

    protected $conditionParser;

    public function getConditionParser()
    {
        return $this->conditionParser;
    }

    public function setConditionParser(ConditionParser $conditionParser)
    {
        $this->conditionParser = $conditionParser;
    }

    protected $tableParser;

    public function getTableParser()
    {
        return $this->tableParser;
    }

    public function setTableParser(TableParser $tableParser)
    {
        $this->tableParser = $tableParser;
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
        return is_int($tokens->isTokenNum(SqlToken::T_SELECT(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_SELECT(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        // catch both cases when select is current AND when its next token.
        $tokens->seekTokenNum(SqlToken::T_SELECT());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_SELECT()) {
            throw new ErrorException("Tried to convert select-sql to job when sql-token-iterator does not point to T_SELECT!");
        }
        
        $entitySelect = new SelectStatement();
        
        ### SPECIAL FLAGS
        
        foreach ([
            [SpecialFlags::FLAG_ALL()                , SqlToken::T_ALL()],
            [SpecialFlags::FLAG_DISTINCT()           , SqlToken::T_DISTINCT()],
            [SpecialFlags::FLAG_DISTINCTROW()        , SqlToken::T_DISTINCTROW()],
            [SpecialFlags::FLAG_HIGH_PRIORITY()      , SqlToken::T_HIGH_PRIORITY()],
            [SpecialFlags::FLAG_STRAIGHT_JOIN()      , SqlToken::T_STRAIGHT_JOIN()],
            [SpecialFlags::FLAG_SQL_SMALL_RESULT()   , SqlToken::T_SQL_SMALL_RESULT()],
            [SpecialFlags::FLAG_SQL_BIG_RESULT()     , SqlToken::T_SQL_BIG_RESULT()],
            [SpecialFlags::FLAG_SQL_BUFFER_RESULT()  , SqlToken::T_SQL_BUFFER_RESULT()],
            [SpecialFlags::FLAG_SQL_CACHE()          , SqlToken::T_SQL_CACHE()],
            [SpecialFlags::FLAG_SQL_NO_CACHE()       , SqlToken::T_SQL_NO_CACHE()],
            [SpecialFlags::FLAG_SQL_CALC_FOUND_ROWS(), SqlToken::T_SQL_CALC_FOUND_ROWS()],
        ] as $pair) {
            list($flagValue, $tokenNum) = $pair;
            
            if ($tokens->seekTokenNum($tokenNum)) {
                $entitySelect->addFlag($flagValue);
            }
        }
        
        ### COLLECT COLUMNS
        
        do {
            try {
                switch(true){
                    
                    # parse jokers like: fooTable.*
                    case is_int($tokens->isTokenText('*', TokenIterator::NEXT, [T_STRING, '.'])):
                        if ($this->tableParser->canParseTokens($tokens)) {
                            $tableFilter = $this->tableParser->convertSqlToJob($tokens);
                        } else {
                            $tableFilter = null;
                        }
                        $tokens->seekTokenText('*', TokenIterator::NEXT, [T_STRING, '.']);
                        $entitySelect->addColumnAllTable($tableFilter);
                        break;
                        
                    case $this->valueParser->canParseTokens($tokens):
                        $value = $this->valueParser->convertSqlToJob($tokens);
                        if ($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])) {
                            $entitySelect->addColumnValue($value, $tokens->getCurrentTokenString());
                        } else {
                            $entitySelect->addColumnValue($value);
                        }
                        break;
                        
                    default:
                        throw new MalformedSql("Non-column-sql found in column-part of select!", $tokens);
                }
            } catch (MalformedSql $exception) {
                throw new MalformedSql($exception->getMessage(), $tokens);
            }
        } while ($tokens->seekTokenText(','));
        
        ### COLLECT TABLES
        
        if ($tokens->seekTokenNum(SqlToken::T_FROM())) {
            if (!$this->joinParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid join definition after FROM in SELECT statement!", $tokens);
            }
            $entitySelect->setJoinDefinition($this->joinParser->convertSqlToJob($tokens));
        }
        
        ### PREPENDED CONDITION (WHERE)
        
        if ($tokens->seekTokenNum(SqlToken::T_WHERE())) {
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing condition for WHERE clause in SELECT statement!", $tokens);
            }
            
            $entitySelect->setCondition($this->valueParser->convertSqlToJob($tokens));
        }
        
        ### GROUP
        
        if ($tokens->seekTokenNum(SqlToken::T_GROUP())) {
            if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                throw new MalformedSql("Missing BY after GROUP in SELECT statement!", $tokens);
            }
            do {
                $groupingDefinition = new GroupingDefinition();

                if (!$this->columnParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Invalid grouping value in SELECT statement!!", $tokens);
                }

                $groupingDefinition->setValue($this->columnParser->convertSqlToJob($tokens));
                
                if ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                    $groupingDefinition->setDirection(SqlToken::T_DESC());
                    
                } elseif ($tokens->seekTokenNum(SqlToken::T_ASC())) {
                    $groupingDefinition->setDirection(SqlToken::T_ASC());
                }

                $entitySelect->addGrouping($groupingDefinition);
                
            } while ($tokens->seekTokenText(','));
        }
        
        ### APPENDED CONDITION (HAVING)
        
        if ($tokens->seekTokenNum(SqlToken::T_HAVING())) {
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing condition for WHERE clause in SELECT statement!", $tokens);
            }
                
            $condition = new ConditionJob();
            $condition->setFirstParameter($this->valueParser->convertSqlToJob($tokens));
                
            $entitySelect->setResultFilter($condition);
        }
        
        ### ORDER
        
        if ($tokens->seekTokenNum(SqlToken::T_ORDER())) {
            if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                throw new MalformedSql("Missing BY after ORDER on SELECT statement!", $tokens);
            }
            do {
                if (!$this->valueParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing value for ORDER BY part on SELECT statement!", $tokens);
                }
                
                $orderValue = $this->valueParser->convertSqlToJob($tokens);
                if ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                    $entitySelect->addOrderColumn($orderValue, SqlToken::T_DESC());
                        
                } else {
                    $tokens->seekTokenNum(SqlToken::T_ASC());
                    $entitySelect->addOrderColumn($orderValue, SqlToken::T_ASC());
                }
                
            } while ($tokens->seekTokenText(','));
        }
        
        ### LIMIT
        
        if ($tokens->seekTokenNum(SqlToken::T_LIMIT())) {
            if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                throw new MalformedSql("Missing offset number for LIMIT part in SELECT statement!", $tokens);
            }
            $entitySelect->setLimitOffset((int)$tokens->getCurrentTokenString());
            if ($tokens->seekTokenText(',')) {
                if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                    throw new MalformedSql("Missing length number for LIMIT part in SELECT statement!", $tokens);
                }
                $entitySelect->setLimitRowCount((int)$tokens->getCurrentTokenString());
            }
        }
        
        ### PROCEDURE
        
        if ($tokens->seekTokenNum(SqlToken::T_PROCEDURE())) {
            if (!$functionParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid procedure specifier after PROCEDURE!", $tokens);
            }
            $entitySelect->setProcedure($functionParser->convertSqlToJob($tokens));
        }
        
        ### INTO OUTFILE|DUMPFILE
        
        if ($tokens->seekTokenNum(SqlToken::T_INTO())) {
            if (!$tokens->seekTokenNum(SqlToken::T_OUTFILE()) && !$tokens->seekTokenNum(SqlToken::T_DUMPFILE())) {
                throw new MalformedSql("Missing OUTFILE or DUMPFILE after INTO!", $tokens);
            }
            if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                throw new MalformedSql("Missing escaped string after INTO OUTFILE!");
            }
            $entitySelect->setIntoOutFile($tokens->seekTokenText($searchToken));
        }
        
        ### FOR UPDATE
        
        if ($tokens->seekTokenNum(SqlToken::T_FOR())) {
            if (!$tokens->seekTokenNum(SqlToken::T_UPDATE())) {
                throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
            }
            $entitySelect->setIsForUpdate(true);
        }
        
        ### LOCK IN SHARE MODE
        
        if ($tokens->seekTokenNum(SqlToken::T_LOCK())) {
            if (!$tokens->seekTokenNum(SqlToken::T_IN())) {
                throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
            }
            if (!$tokens->seekTokenNum(SqlToken::T_SHARE())) {
                throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
            }
            if (!$tokens->seekTokenNum(SqlToken::T_MODE())) {
                throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
            }
            $entitySelect->setIsLockInShareMode(true);
        }
        
        ### UNION
        
        if ($tokens->seekTokenNum(SqlToken::T_UNION())) {
            $isUnionAll      = $tokens->seekTokenNum(SqlToken::T_ALL());
            $isUnionDistinct = $tokens->seekTokenNum(SqlToken::T_DISTINCT());
            $isUnionAll      = $isUnionAll || $tokens->seekTokenNum(SqlToken::T_ALL());
            
            if ($isUnionAll && $isUnionDistinct) {
                throw new MalformedSql("UNION cannot be ALL and DISTINCT at the same time!", $tokens);
            }
            
            $isUnionInParenthesis = $tokens->seekTokenText('(');
            
            if (!$this->canParseTokens($tokens)) {
                throw new MalformedSql("Missing following SELECT statement after UNION in SELECT statement!", $tokens);
            }
            $entitySelect->setUnionSelect($this->convertSqlToJob($tokens), $isUnionDistinct);
            
            if ($isUnionInParenthesis && !$tokens->seekTokenText(')')) {
                throw new MalformedSql("Missing ending parenthesis after UNION in SELECT statement!", $tokens);
            }
        }
        
        return $entitySelect;
    }
}
