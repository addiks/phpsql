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

use Addiks\PHPSQL\Entity\Job\Statement\DropStatement;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

class DropSqlParser extends SqlParser
{
    
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
        
        /* @var $dropJob DropStatement */
        $this->factorize($dropJob);
        
        if ($tokens->seekTokenNum(SqlToken::T_TEMPORARY())) {
            $dropJob->setIsTemporary(true);
        }
        
        switch(true){
            case $tokens->seekTokenNum(SqlToken::T_SCHEMA(), TokenIterator::NEXT):
            case $tokens->seekTokenNum(SqlToken::T_DATABASE()):
                $dropJob->setType(Drop::TYPE_DATABASE);
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_TABLE()):
                $dropJob->setType(Drop::TYPE_TABLE);
                break;
            
            case $tokens->seekTokenNum(SqlToken::T_VIEW()):
                $dropJob->setType(Drop::TYPE_VIEW);
                break;
        }
        
        if ($tokens->seekTokenNum(SqlToken::T_IF())) {
            if (!$tokens->seekTokenNum(SqlToken::T_EXISTS())) {
                throw new MalformedSql("Malformed drop-statement (missing T_EXISTS after T_IF)!");
            }
            $dropJob->setOnlyIfExist(true);
        } else {
            $dropJob->setOnlyIfExist(false);
        }
        
        do {
            if (!$tokens->seekTokenNum(T_STRING)) {
                throw new MalformedSql("Missing a subject to drop for T_DROP statement!", $tokens);
            }
            
            $subject = $tokens->getCurrentTokenString();
            $dropJob->addSubject($subject);
        } while ($tokens->seekTokenText(','));
        
        if ($tokens->seekTokenNum(SqlToken::T_RESTRICT())) {
            $dropJob->setReferenceOption(ReferenceOption::RESTRICT());
            if ($tokens->seekTokenNum(SqlToken::T_CASCADE())) {
                throw new MalformedSql("Conflicting T_RESTRICT with T_CASCADE!", $tokens);
            }
        }if ($tokens->seekTokenNum(SqlToken::T_CASCADE())) {
            $dropJob->setReferenceOption(ReferenceOption::CASCADE());
            if ($tokens->seekTokenNum(SqlToken::T_RESTRICT())) {
                throw new MalformedSql("Conflicting T_RESTRICT with T_CASCADE!", $tokens);
            }
        }
        
        return $dropJob;
    }
}
