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

use Addiks\PHPSQL\Entity\Job\Statement\DeleteStatement;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\SqlParser\Part\JoinDefinitionParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;

class DeleteSqlParser extends SqlParser
{
    
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

    protected $valueParser;

    public function getValueParser()
    {
        return $this->valueParser;
    }

    public function setValueParser(ValueParser $valueParser)
    {
        $this->valueParser = $valueParser;
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

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_DELETE(), TokenIterator::NEXT))
            || is_int($tokens->isTokenNum(SqlToken::T_DELETE(), TokenIterator::CURRENT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_DELETE());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_DELETE()) {
            throw new ErrorException("Tried to parse DELETE statement when token iterator is not at T_DELETE!");
        }
        
        $deleteJob = new DeleteStatement();
        
        if ($tokens->seekTokenNum(SqlToken::T_LOW_PRIORITY())) {
            $deleteJob->setIsLowPriority(true);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_QUICK())) {
            $deleteJob->setIsQuick(true);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_IGNORE())) {
            $deleteJob->setIsIgnore(true);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_FROM())) {
            do {
                if (!$this->tableParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid table specifier in DELETE statement!", $tokens);
                }
                $deleteJob->addDeleteTable($this->tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText('.')) {
                    if (!$tokens->seekTokenText('*')) {
                        throw new MalformedSql("Only '*' allowed for column specification in DELETE statement!", $tokens);
                    }
                }
            } while ($tokens->seekTokenText(','));
            
            if ($tokens->seekTokenNum(SqlToken::T_USING())) {
                if (!$this->joinParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid JOIN definition after USING in DELETE statement!", $tokens);
                }
                $deleteJob->setJoinDefinition($this->joinParser->convertSqlToJob($tokens));
            }
            
        } else {
            do {
                if (!$this->tableParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid table specifier in DELETE statement!", $tokens);
                }
                $deleteJob->addDeleteTable($this->tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText('.')) {
                    if (!$tokens->seekTokenText('*')) {
                        throw new MalformedSql("Only '*' allowed for column specification in DELETE statement!", $tokens);
                    }
                }
            } while ($tokens->seekTokenText(','));

            if ($tokens->seekTokenNum(SqlToken::T_FROM())) {
                if (!$this->joinParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid JOIN definition after FROM in DELETE statement!", $tokens);
                }
                $deleteJob->setJoinDefinition($this->joinParser->convertSqlToJob($tokens));
            }
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_WHERE())) {
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing condition for WHERE clause in UPDATE statement!", $tokens);
            }
        
            $deleteJob->setCondition($this->valueParser->convertSqlToJob($tokens));
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_ORDER())) {
            if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                throw new MalformedSql("Missing BY after ORDER on DELETE statement!", $tokens);
            }
            if (!$columnParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing column specifier for ORDER BY part on DELETE statement!", $tokens);
            }
            $deleteJob->setOrderColumn($columnParser->convertSqlToJob($tokens));
                
            if ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                $deleteJob->setOrderDirection(SqlToken::T_DESC());
        
            } elseif ($tokens->seekTokenNum(SqlToken::T_ASC())) {
                $deleteJob->setOrderDirection(SqlToken::T_ASC());
            }
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_LIMIT())) {
            if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                throw new MalformedSql("Missing offset number for LIMIT part in DELETE statement!", $tokens);
            }
            $deleteJob->setLimitOffset((int)$tokens->getCurrentTokenString());
            if ($tokens->seekTokenText(',')) {
                if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                    throw new MalformedSql("Missing length number for LIMIT part in DELETE statement!", $tokens);
                }
                $deleteJob->setLimitRowCount((int)$tokens->getCurrentTokenString());
            }
        }
        
        return $deleteJob;
    }
}
