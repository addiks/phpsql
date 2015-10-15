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

use Addiks\PHPSQL\Value\Enum\Sql\Show\ShowType;
use Addiks\PHPSQL\Job\Statement\ShowStatement;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\SqlParser;

use Addiks\PHPSQL\Iterators\TokenIterator;

class ShowSqlParser extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_SHOW(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_SHOW(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_SHOW());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_SHOW()) {
            throw new ErrorException("Tried to convert sql-show to job-entity when tokeniterator does not point to T_SHOW!");
        }
        
        $showJob = new ShowStatement();
        
        switch(true){

            case $tokens->seekTokenNum(SqlToken::T_DATABASES()):
                $showJob->setType(ShowType::DATABASES());
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_TABLES()):
                $showJob->setType(ShowType::TABLES());
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_VIEWS()):
                $showJob->setType(ShowType::VIEWS());
                break;
            
            default:
                throw new MalformedSqlException("Invalid parameter for show-statement!", $tokens);
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_FROM())) {
            if (!$tokens->seekTokenNum(T_STRING)) {
                throw new MalformedSqlException("Missing database name after FROM in SHOW statement!");
            }
            
            $showJob->setDatabase($tokens->getCurrentTokenString());
        }
        
        return $showJob;
    }
}
