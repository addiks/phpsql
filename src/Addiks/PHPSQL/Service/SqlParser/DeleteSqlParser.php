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

namespace Addiks\PHPSQL\Service\SqlParser;

use Addiks\PHPSQL\Entity\Job\Statement\DeleteStatement;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

class DeleteSqlParser extends SqlParser
{
    
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
        
        /* @var $conditionParser Condition */
        $this->factorize($conditionParser);
        
        /* @var $tableParser TableParser */
        $this->factorize($tableParser);
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $joinParser JoinDefinition */
        $this->factorize($joinParser);
        
        /* @var $deleteJob DeleteStatement */
        $this->factorize($deleteJob);
        
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
                if (!$tableParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid table specifier in DELETE statement!", $tokens);
                }
                $deleteJob->addDeleteTable($tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText('.')) {
                    if (!$tokens->seekTokenText('*')) {
                        throw new MalformedSql("Only '*' allowed for column specification in DELETE statement!", $tokens);
                    }
                }
            } while ($tokens->seekTokenText(','));
            
            if ($tokens->seekTokenNum(SqlToken::T_USING())) {
                if (!$joinParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid JOIN definition after USING in DELETE statement!", $tokens);
                }
                $deleteJob->setJoinDefinition($joinParser->convertSqlToJob($tokens));
            }
            
        } else {
            do {
                if (!$tableParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid table specifier in DELETE statement!", $tokens);
                }
                $deleteJob->addDeleteTable($tableParser->convertSqlToJob($tokens));
                if ($tokens->seekTokenText('.')) {
                    if (!$tokens->seekTokenText('*')) {
                        throw new MalformedSql("Only '*' allowed for column specification in DELETE statement!", $tokens);
                    }
                }
            } while ($tokens->seekTokenText(','));

            if ($tokens->seekTokenNum(SqlToken::T_FROM())) {
                if (!$joinParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid JOIN definition after FROM in DELETE statement!", $tokens);
                }
                $deleteJob->setJoinDefinition($joinParser->convertSqlToJob($tokens));
            }
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_WHERE())) {
            if (!$valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing condition for WHERE clause in UPDATE statement!", $tokens);
            }
        
            $deleteJob->setCondition($valueParser->convertSqlToJob($tokens));
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
