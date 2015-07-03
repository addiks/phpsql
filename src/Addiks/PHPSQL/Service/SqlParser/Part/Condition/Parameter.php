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

namespace Addiks\PHPSQL\Service\SqlParser\Part\Condition;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;
use Addiks\PHPSQL\Value\Enum\Sql\Condition\Parameter as ParameterValue;

class Parameter extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        try {
            return !is_null(ParameterValue::getByValue(strtolower($tokens->getExclusiveTokenString())));
        } catch (\Exception $exception) {
            return false;
        }
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $parameterCondition Parameter */
        $this->factorize($parameterCondition);
        
        try {
            $parameter = Parameter::getByValue(strtolower($tokens->getExclusiveTokenString()));
            if (is_null($parameter)) {
                throw new MalformedSql("Invalid parameter value given for parameter condition!");
            }
            $parameterCondition->setParameter($parameter);
            $tokens->seekIndex($tokens->getExclusiveTokenIndex());
        } catch (MalformedSql $exception) {
            throw new MalformedSql($exception->getMessage(), $tokens);
            
        } catch (\Exception $exception) {
            throw new ErrorException("Tried to parse parameter-condition when token-iterator does not point to valid parameter!");
        }
        
        switch($parameter){
            case Parameter::SEPARATOR:
                if (!$valueParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid value after parameter-condition {$parameterCondition->getParameter()->getValue()}!", $tokens);
                }
                $parameterCondition->setValue($valueParser->convertSqlToJob($tokens));
                break;
        }
        
        return $parameterCondition;
    }
}
