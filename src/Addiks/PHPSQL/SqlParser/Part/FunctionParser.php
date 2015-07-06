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

namespace Addiks\PHPSQL\SqlParser\Part;

use Addiks\PHPSQL\Entity\Job\Part\FunctionJob;

use Addiks\PHPSQL\SqlParser\Part;

use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;

use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\Parameter;

class FunctionParser extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        $indexBefore = $tokens->getIndex();
        
        $tokens->seekIndex($tokens->getExclusiveTokenIndex());
        
        if ($tokens->getCurrentTokenNumber() !== T_STRING && !($tokens->getCurrentTokenNumber() instanceof Token)) {
            $tokens->seekIndex($indexBefore);
            return false;
        }
        
        $paranthesisExist = is_int($tokens->isTokenText('('));
        
        $tokens->seekIndex($indexBefore);
        return $paranthesisExist;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        if (!$this->canParseTokens($tokens)) {
            throw new ErrorException("Tried to convert sql function to job entity when token index is not at function!");
        }
        
        /* @var $selectParser SelectSqlParser */
        $this->factorize($selectParser);
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $parameterConditionParser Parameter */
        $this->factorize($parameterConditionParser);
        
        /* @var $functionJob FunctionJob */
        $this->factorize($functionJob);
        
        $tokens->seekIndex($tokens->getExclusiveTokenIndex());
        
        $functionJob->setName($tokens->getCurrentTokenString());
        
        if (!$tokens->seekTokenText('(')) {
            throw new MalformedSql("Missing beginning parenthesis for argument-list in function call!", $tokens);
        }
        if (!$tokens->seekTokenText(')')) {
            do {
                try {
                    while ($parameterConditionParser->canParseTokens($tokens)) {
                        $functionJob->addParameter($parameterConditionParser->convertSqlToJob($tokens));
                    }
                    switch(true){
                        
                        case $valueParser->canParseTokens($tokens):
                            $functionJob->addArgumentValue($valueParser->convertSqlToJob($tokens));
                            break;
                        
                        case $selectParser->canParseTokens($tokens):
                            $functionJob->addArgumentValue($selectParser->convertSqlToJob($tokens));
                            break;
                            
                        case $tokens->seekTokenText('*'):
                            $functionJob->addArgumentValue('*');
                            break;
                            
                        default:
                            throw new MalformedSql("Invalid argument defintion in function call!", $tokens);
                    }
                    while ($parameterConditionParser->canParseTokens($tokens)) {
                        $functionJob->addParameter($parameterConditionParser->convertSqlToJob($tokens));
                    }
                } catch (\ErrorException $exception) {
                    throw new MalformedSql($exception->getMessage(), $tokens);
                }
            } while ($tokens->seekTokenText(','));
            if (!$tokens->seekTokenText(')')) {
                throw new MalformedSql("Missing ending parenthesis for argument-list in function call!", $tokens);
            }
        }
        
        return $functionJob;
    }
}
