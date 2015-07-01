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

use Addiks\PHPSQL\Service\SqlParser\Part\Specifier\TableParser;

use Addiks\PHPSQL\Entity\Job\Statement\DescribeStatement;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

class DescribeSqlParser extends SqlParser
{
    
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
            throw new Error("Tried to parse DESCRIBE statement when token-iterator does not point to DESC or DESCRIBE!");
        }
        
        /* @var $tableParser TableParser */
        $this->factorize($tableParser);
        
        if (!$tableParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing table-specifier for DESCRIBE statement!", $tokens);
        }
        
        /* @var $describeJob DescribeStatement */
        $this->factorize($describeJob);
        
        $describeJob->setTable($tableParser->convertSqlToJob($tokens));
        
        if ($tokens->seekTokenText('wild')) {
            $describeJob->setIsWild(true);
            
        } elseif ($tokens->seekTokenNum(T_STRING)) {
            $describeJob->setColumnName($tokens->getCurrentTokenString());
        }
        
        return $describeJob;
    }
}
