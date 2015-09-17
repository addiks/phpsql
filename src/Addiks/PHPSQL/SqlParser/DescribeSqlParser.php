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

use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\Entity\Job\Statement\DescribeStatement;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;

class DescribeSqlParser extends SqlParser
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

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_DESCRIBE(), TokenIterator::NEXT))
            || is_int($tokens->isTokenNum(SqlToken::T_DESCRIBE(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_DESC(), TokenIterator::NEXT))
            || is_int($tokens->isTokenNum(SqlToken::T_DESC(), TokenIterator::CURRENT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_DESCRIBE());
        $tokens->seekTokenNum(SqlToken::T_DESC());
        
        if (!in_array($tokens->getCurrentTokenNumber(), [SqlToken::T_DESCRIBE(), SqlToken::T_DESC()])) {
            throw new ErrorException("Tried to parse DESCRIBE statement when token-iterator does not point to DESC or DESCRIBE!");
        }
        
        if (!$this->tableParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing table-specifier for DESCRIBE statement!", $tokens);
        }
        
        $describeJob = new DescribeStatement();
        $describeJob->setTable($this->tableParser->convertSqlToJob($tokens));
        
        if ($tokens->seekTokenText('wild')) {
            $describeJob->setIsWild(true);
            
        } elseif ($tokens->seekTokenNum(T_STRING)) {
            $describeJob->setColumnName($tokens->getCurrentTokenString());
        }
        
        return $describeJob;
    }
}
