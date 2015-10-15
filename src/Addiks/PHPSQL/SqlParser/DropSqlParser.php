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

use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\SqlParser;
use Addiks\PHPSQL\Job\Statement\DropStatement;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;

class DropSqlParser extends SqlParser
{
    
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
        return is_int($tokens->isTokenNum(SqlToken::T_DROP(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_DROP(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $tokens->seekTokenNum(SqlToken::T_DROP());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_DROP()) {
            throw new ErrorException("Tried to parse sql-drop when token-iterator does not point to T_DROP!");
        }
        
        $dropJob = new DropStatement();
        
        if ($tokens->seekTokenNum(SqlToken::T_TEMPORARY())) {
            $dropJob->setIsTemporary(true);
        }
        
        switch(true){
            case $tokens->seekTokenNum(SqlToken::T_SCHEMA(), TokenIterator::NEXT):
            case $tokens->seekTokenNum(SqlToken::T_DATABASE()):
                $dropJob->setType(DropStatement::TYPE_DATABASE);
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_TABLE()):
                $dropJob->setType(DropStatement::TYPE_TABLE);
                break;
            
            case $tokens->seekTokenNum(SqlToken::T_VIEW()):
                $dropJob->setType(DropStatement::TYPE_VIEW);
                break;
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_IF())) {
            if (!$tokens->seekTokenNum(SqlToken::T_EXISTS())) {
                throw new MalformedSqlException("Malformed drop-statement (missing T_EXISTS after T_IF)!");
            }
            $dropJob->setOnlyIfExist(true);
        } else {
            $dropJob->setOnlyIfExist(false);
        }
        
        do {
            if (!$this->valueParser->canParseTokens($tokens)) {
                throw new MalformedSqlException("Missing a subject to drop for T_DROP statement!", $tokens);
            }
            
            $subject = $this->valueParser->convertSqlToJob($tokens);
            $dropJob->addSubject($subject);
        } while ($tokens->seekTokenText(','));
        
        if ($tokens->seekTokenNum(SqlToken::T_RESTRICT())) {
            $dropJob->setReferenceOption(ReferenceOption::RESTRICT());
            if ($tokens->seekTokenNum(SqlToken::T_CASCADE())) {
                throw new MalformedSqlException("Conflicting T_RESTRICT with T_CASCADE!", $tokens);
            }
        }if ($tokens->seekTokenNum(SqlToken::T_CASCADE())) {
            $dropJob->setReferenceOption(ReferenceOption::CASCADE());
            if ($tokens->seekTokenNum(SqlToken::T_RESTRICT())) {
                throw new MalformedSqlException("Conflicting T_RESTRICT with T_CASCADE!", $tokens);
            }
        }
        
        return $dropJob;
    }
}
