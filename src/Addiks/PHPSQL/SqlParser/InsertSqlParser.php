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

use Addiks\PHPSQL\Entity\Job\Insert\DataChange;

use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;

use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;

use Addiks\PHPSQL\SqlParser\Part\FunctionParser;

use Addiks\PHPSQL\SqlParser\Part\ValueParser;

use Addiks\PHPSQL\Entity\Job\Statement\InsertStatement;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\TokenIterator;

use Addiks\PHPSQL\SQLTokenIterator;

use Addiks\PHPSQL\SqlParser;

class InsertSqlParser extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_INSERT(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_INSERT(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_INSERT());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_INSERT()) {
            throw new ErrorException("Tried to parse INSERT statement when token-iterator is not at INSERT!");
        }
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $functionParser FunctionParser */
        $this->factorize($functionParser);
        
        /* @var $tableParser TableParser */
        $this->factorize($tableParser);
        
        /* @var $columnParser ColumnParser */
        $this->factorize($columnParser);
        
        /* @var $selectParser SelectSqlParser */
        $this->factorize($selectParser);
        
        /* @var $dataChange DataChange */
        $this->factorize($dataChange);
        
        /* @var $insertJob InsertStatement */
        $this->factorize($insertJob);
        
        switch(true){
            case $tokens->seekTokenNum(SqlToken::T_LOW_PRIORITY()):
                $insertJob->setPriority(Priority::LOW_PRIORITY());
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_DELAYED()):
                $insertJob->setPriority(Priority::DELAYED());
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_HIGH_PRIORITY()):
                $insertJob->setPriority(Priority::HIGH_PRIORITY());
                break;
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_IGNORE())) {
            $insertJob->setDoIgnoreErrors(true);
        }
        
        if (!$tokens->seekTokenNum(SqlToken::T_INTO())) {
            throw new MalformedSql("Missing INTO after INSERT for INSERT INTO statement!", $tokens);
        }
        
        if (!$tableParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing table-specifier for INSERT INTO statement!", $tokens);
        }
        $insertJob->setTable($tableParser->convertSqlToJob($tokens));
        
        if ($tokens->seekTokenText('(')) {
            do {
                if (!$columnParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid column name in column selection for INSERT INTO statement!", $tokens);
                }
                $insertJob->addColumnSelection($columnParser->convertSqlToJob($tokens));
            } while ($tokens->seekTokenText(','));
            
            if (!$tokens->seekTokenText(')')) {
                throw new MalformedSql("Missing closing parenthesis after column-selection for INSERT INTO statement!");
            }
            
            if ($tokens->seekTokenNum(SqlToken::T_VALUES())) {
                do {
                    if (!$tokens->seekTokenText('(')) {
                        throw new MalformedSql("Missing begin parenthesis in value definiton for INSERT INTO statement!", $tokens);
                    }
                    $dataRow = array();
                    do {
                        switch(true){
                            
                            case $valueParser->canParseTokens($tokens):
                                $dataRow[] = $valueParser->convertSqlToJob($tokens);
                                break;
                            
                            default:
                                throw new MalformedSql("Invalid value in value-defintion for INSERT INTO statement!", $tokens);
                        }
                    } while ($tokens->seekTokenText(','));
                    if (!$tokens->seekTokenText(')')) {
                        throw new MalformedSql("Missing ending parenthesis in value definiton for INSERT INTO statement!", $tokens);
                    }
                    $insertJob->addDataSourceValuesRow($dataRow);
                } while ($tokens->seekTokenText(','));
                                
            } elseif ($selectParser->canParseTokens($tokens)) {
                $insertJob->setDataSourceSelect($selectParser->convertSqlToJob($tokens));
                
            } else {
                throw new MalformedSql("Invalid data-source-definiton (VALUES or SELECT) in INSERT INTO statement!", $tokens);
            }
            
        } elseif ($tokens->seekTokenNum(SqlToken::T_SET())) {
            do {
                if (!$columnParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing column specifier for INSERT INTO SET statement!", $tokens);
                }
                $dataChange->setColumn($columnParser->convertSqlToJob($tokens));
                if (!$tokens->seekTokenText('=')) {
                    throw new MalformedSql("Missing '=' in INSERT INTO SET statement!", $tokens);
                }
                switch(true){
                    case $valueParser->canParseTokens($tokens):
                        $dataChange->setValue($valueParser->convertSqlToJob($tokens));
                        break;
                    default:
                        throw new MalformedSql("Invalid value for INSERT INTO SET statement!", $tokens);
                }
                $insertJob->addColumnSetValue(clone $dataChange);
            } while ($tokens->seekTokenText(','));
            
        } elseif ($selectParser->canParseTokens($tokens)) {
            $insertJob->setDataSourceSelect($selectParser->convertSqlToJob($tokens));
            
        } else {
            throw new MalformedSql("Invalid column-selection for INSERT INTO statement!", $tokens);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_ON())) {
            if (!$tokens->seekTokenNum(SqlToken::T_DUPLICATE())) {
                throw new MalformedSql("Missing DUPLICATE in INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
            }
            if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                throw new MalformedSql("Missing KEY in INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
            }
            if (!$tokens->seekTokenNum(SqlToken::T_UPDATE())) {
                throw new MalformedSql("Missing UPDATE in INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
            }
            
            do {
                if (!$columnParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing column specifier for INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
                }
                $dataChange->setColumn($columnParser->convertSqlToJob($tokens));
                if (!$tokens->seekTokenText('=')) {
                    throw new MalformedSql("Missing '=' in INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
                }
                switch(true){
                    case $valueParser->canParseTokens($tokens):
                        $dataChange->setValue($valueParser->convertSqlToJob($tokens));
                        break;
                    default:
                        throw new MalformedSql("Invalid value for INSERT INTO ON DUPLICATE KEY UPDATE statement!", $tokens);
                }
                $insertJob->addOnDuplicateDataChange(clone $dataChange);
            } while ($tokens->seekTokenText(','));
                
        }
        
        return $insertJob;
    }
}
