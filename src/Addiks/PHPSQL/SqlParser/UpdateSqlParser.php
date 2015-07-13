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

use Addiks\PHPSQL\SqlParser\Part\FunctionParser;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\Entity\Job\Statement\UpdateStatement;
use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;

class UpdateSqlParser extends SqlParser
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

    protected $columnParser;

    public function getColumnParser()
    {
        return $this->columnParser;
    }

    public function setColumnParser(ColumnParser $columnParser)
    {
        $this->columnParser = $columnParser;
    }

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_UPDATE(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_UPDATE(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_UPDATE());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_UPDATE()) {
            throw new ErrorException("Tried to parse update statement when token-iterator does not point to T_UPDATE!");
        }
        
        $dataChange = new DataChange();
        $updateJob = new UpdateStatement();
        
        if ($tokens->seekTokenNum(SqlToken::T_LOW_PRIORITY())) {
            $updateJob->setIsLowPriority(true);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_IGNORE())) {
            $updateJob->setDoIgnoreErrors(true);
        }
        
        do {
            if (!$this->tableParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing table specifier in UPDATE statement!", $tokens);
            }
            $updateJob->addTable($this->tableParser->convertSqlToJob($tokens));
        } while ($tokens->seekTokenText(','));
        
        if (!$tokens->seekTokenNum(SqlToken::T_SET())) {
            throw new MalformedSql("Missing SET after table specifier in UPDATE statement!", $tokens);
        }
        
        do {
            if (!$this->columnParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing column specifier for SET part in UPDATE statement!", $tokens);
            }
            $dataChange->setColumn($this->columnParser->convertSqlToJob($tokens));
            
            if (!$tokens->seekTokenText('=')) {
                throw new MalformedSql("Missing '=' on SET part in UPDATE statement!", $tokens);
            }
            
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("MIssing valid value on SET part in UPDATE statement!", $tokens);
            }
            
            $dataChange->setValue($this->valueParser->convertSqlToJob($tokens));
            
            $updateJob->addDataChange(clone $dataChange);
        } while ($tokens->seekTokenText(','));
        
        if ($tokens->seekTokenNum(SqlToken::T_WHERE())) {
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing condition for WHERE clause in UPDATE statement!", $tokens);
            }
                
            $updateJob->setCondition($this->valueParser->convertSqlToJob($tokens));
        }
        
        
        if ($tokens->seekTokenNum(SqlToken::T_ORDER())) {
            if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                throw new MalformedSql("Missing BY after ORDER on UPDATE statement!", $tokens);
            }
            if (!$this->columnParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing column specifier for ORDER BY part on UPDATE statement!", $tokens);
            }
            $updateJob->setOrderColumn($this->columnParser->convertSqlToJob($tokens));
            
            if ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                $updateJob->setOrderDirection(SqlToken::T_DESC());
                
            } elseif ($tokens->seekTokenNum(SqlToken::T_ASC())) {
                $updateJob->setOrderDirection(SqlToken::T_ASC());
            }
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_LIMIT())) {
            if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                throw new MalformedSql("Missing offset number for LIMIT part in UPDATE statement!", $tokens);
            }
            $updateJob->setLimitOffset((int)$tokens->getCurrentTokenString());
            if ($tokens->seekTokenText(',')) {
                if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                    throw new MalformedSql("Missing length number for LIMIT part in UPDATE statement!", $tokens);
                }
                $updateJob->setLimitRowCount((int)$tokens->getCurrentTokenString());
            }
        }
        
        return $updateJob;
    }
}
