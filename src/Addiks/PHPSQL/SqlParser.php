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

namespace Addiks\PHPSQL;

use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\TokenIterator;
use Addiks\Database\Entity\Job\Part\ParenthesisPart;

/**
 * This is a parser for SQL statements.
 * You give it an SQL statement in form of a SQL-Token-Iterator,
 * and it either throws an MalformedSql exception or returnes an Job-Entity.
 *
 * Technically it acts as a hub for the concrete parsers (select-parser, insert-parser, create-parser, ...).
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
            throw new ErrorException("Class '".get_class($this)."' needs to declare an own method '".__FUNCTION__."'!");
        }
        
        /* @var $jobEntity Job */
        $jobEntities = array();
        
    #   $tokens->seekIndex(-1);
        
        do {
            while ($tokens->seekTokenText(';')) {
            }
        
            $parserFound = false;
            foreach ($this->sqlParser as $sqlParser) {
                /* @var $sqlParser SqlParser */

                if ($sqlParser->canParseTokens($tokens)) {
                    $parserFound = true;
                    $jobEntity = $sqlParser->convertSqlToJob($tokens);
                    while ($jobEntity instanceof ParenthesisPart) {
                        $jobEntity = $jobEntity->getContain();
                    }
                    $jobEntities[] = $jobEntity;
                    break;
                }
            }

            if (!$parserFound) {
                if (is_null($tokens->getExclusiveTokenNumber()) || $tokens->isAtEnd()) {
                    break;
                } else {
                    $relevantToken = $tokens->getExclusiveTokenString();
                    throw new MalformedSql("Invalid SQL-statement! (Cannot extract command: '{$relevantToken}')", $tokens);
                }
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

    protected $sqlParser = array();
    
    public function addSqlParser(self $sqlParser)
    {
        $this->sqlParser[get_class($sqlParser)] = $sqlParser;
    }

    public function getSqlParserByClass($className)
    {
        $sqlParser = null;

        if (isset($this->sqlParser[$className])) {
            $sqlParser = $this->sqlParser[$className];
        }

        if (is_null($sqlParser) && !is_null($this->getParentSqlParser())) {
            $sqlParser = $this->getParentSqlParser()->getSqlParserByClass($className);
        }

        return $sqlParser;
    }

    /**
     * The parent sql-parser.
     * (Normally the sql-parser-hub)
     *
     * @var SqlParser
     */
    protected $parentSqlParser;

    public function getParentSqlParser()
    {
        return $this->parentSqlParser;
    }

    public function setParentSqlParser(SqlParser $sqlParser)
    {
        $this->parentSqlParser = $sqlParser;
    }
}
