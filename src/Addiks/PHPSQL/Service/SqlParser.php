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

namespace Addiks\PHPSQL\Service;

use Addiks\PHPSQL\Service\SqlParser\DescribeSqlParser;
use Addiks\PHPSQL\Service\SqlParser\SetSqlParser;
use Addiks\PHPSQL\Service\SqlParser\DropSqlParser;
use Addiks\PHPSQL\Service\SqlParser\AlterSqlParser;
use Addiks\PHPSQL\Service\SqlParser\CreateSqlParser;
use Addiks\PHPSQL\Service\SqlParser\UseSqlParser;
use Addiks\PHPSQL\Service\SqlParser\ShowSqlParser;
use Addiks\PHPSQL\Service\SqlParser\DeleteSqlParser;
use Addiks\PHPSQL\Service\SqlParser\UpdateSqlParser;
use Addiks\PHPSQL\Service\SqlParser\InsertSqlParser;
use Addiks\PHPSQL\Service\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\Service\SqlParser\Part\Parenthesis;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Tool\SQLTokenIterator;
use Addiks\PHPSQL\Tool\TokenIterator;

/**
 * This is a parser for SQL statements.
 * You give it an SQL statement in form of a SQL-Token-Iterator,
 * and it either throws an MalformedSql exception or returnes an Job-Entity.
 *
 * Technically it acts as a hub for the concrete parsers (select-parser, insert-parser, create-parser, ...).
 *
 * The job-entity can then be rendered to a php-function executing the requested operation.
 *
 * @see SQLTokenIterator
 * @see Job
 * @see JobRenderer
 */
class SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return true;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        if (get_class($this) !== __CLASS__) {
            throw new Error("Class '".get_class($this)."' needs to declare an own method '".__FUNCTION__."'!");
        }
        
        /* @var $parenthesisParser Parenthesis */
        $this->factorize($parenthesisParser);
        
        /* @var $selectParser SelectSqlParser */
        $this->factorize($selectParser);
        
        /* @var $insertParser InsertSqlParser */
        $this->factorize($insertParser);
        
        /* @var $updateParser UpdateSqlParser */
        $this->factorize($updateParser);
        
        /* @var $deleteParser DeleteSqlParser */
        $this->factorize($deleteParser);
        
        /* @var $showParser ShowSqlParser */
        $this->factorize($showParser);
        
        /* @var $useParser UseSqlParser */
        $this->factorize($useParser);
        
        /* @var $createParser CreateSqlParser */
        $this->factorize($createParser);
        
        /* @var $alterParser AlterSqlParser */
        $this->factorize($alterParser);
        
        /* @var $dropParser DropSqlParser */
        $this->factorize($dropParser);
        
        /* @var $setParser SetSqlParser */
        $this->factorize($setParser);
        
        /* @var $describeParser DescribeSqlParser */
        $this->factorize($describeParser);
        
        /* @var $beginParser BeginSqlParser */
    #   $this->factorize($beginParser);
        
        /* @var $endParser EndSqlParser */
    #   $this->factorize($endParser);
        
        /* @var $converter self */
        $converter = null;
        
        /* @var $jobEntity Job */
        $jobEntities = array();
        
    #   $tokens->seekIndex(-1);
        
        do {
            while ($tokens->seekTokenText(';')) {
            }
        
            switch(true){
                
                ### ETC
                    
                case $parenthesisParser->canParseTokens($tokens, TokenIterator::CURRENT):
                    $parenthesisJob = $parenthesisParser->convertSqlToJob($tokens, TokenIterator::CURRENT);
                    $jobEntities[] = $parenthesisJob->getContain();
                    break;
                    
                    ### DATA
                
                case $selectParser->canParseTokens($tokens):
                    $jobEntities[] = $selectParser->convertSqlToJob($tokens);
                    break;
                
                case $insertParser->canParseTokens($tokens):
                    $jobEntities[] = $insertParser->convertSqlToJob($tokens);
                    break;
                
                case $updateParser->canParseTokens($tokens):
                    $jobEntities[] = $updateParser->convertSqlToJob($tokens);
                    break;
                
                case $deleteParser->canParseTokens($tokens):
                    $jobEntities[] = $deleteParser->convertSqlToJob($tokens);
                    break;
                    
                    ### SCHEMA
                    
                case $describeParser->canParseTokens($tokens):
                    $jobEntities[] = $describeParser->convertSqlToJob($tokens);
                    break;
                    
                case $showParser->canParseTokens($tokens):
                    $jobEntities[] = $showParser->convertSqlToJob($tokens);
                    break;
                    
                case $useParser->canParseTokens($tokens):
                    $jobEntities[] = $useParser->convertSqlToJob($tokens);
                    break;
            
                case $createParser->canParseTokens($tokens):
                    $jobEntities[] = $createParser->convertSqlToJob($tokens);
                    break;
                    
                case $alterParser->canParseTokens($tokens):
                    $jobEntities[] = $alterParser->convertSqlToJob($tokens);
                    break;
                    
                case $dropParser->canParseTokens($tokens):
                    $jobEntities[] = $dropParser->convertSqlToJob($tokens);
                    break;
                    
                    ### CONFIGURATION
                    
                case $setParser->canParseTokens($tokens):
                    $jobEntities[] = $setParser->convertSqlToJob($tokens);
                    break;
                
                    ### TRANSACTION
                    
            #	case $tokens->seekTokenNum(SqlToken::T_BEGIN()):
            #		$converter = $this->factory("Begin");
            #       break;
                    
            #	case $tokens->seekTokenNum(SqlToken::T_END()):
            #		$converter = $this->factory("End");
            #		break;
                    
                case is_null($tokens->getExclusiveTokenNumber()) || $tokens->isAtEnd():
                    break 2;
                    
                default:
                    $relevantToken = $tokens->getExclusiveTokenString();
                    throw new MalformedSql("Invalid SQL-statement! (Cannot extract command: '{$relevantToken}')", $tokens);
            }
        
        } while ($tokens->isTokenText(';'));
        
        if (!$tokens->isAtEnd() && $tokens->getExclusiveTokenIndex() !== $tokens->getIndex()) {
            throw new MalformedSql("Overlapping unparsed SQL at the end of statement!", $tokens);
        }
        
        foreach ($jobEntities as $job) {
            $job->checkPlausibility();
        }
        
        return $jobEntities;
    }
}
