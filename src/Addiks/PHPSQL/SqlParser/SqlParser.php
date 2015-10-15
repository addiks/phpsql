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

use ErrorException;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\SqlParser\AlterSqlParser;
use Addiks\PHPSQL\SqlParser\CreateSqlParser;
use Addiks\PHPSQL\SqlParser\DeleteSqlParser;
use Addiks\PHPSQL\SqlParser\DescribeSqlParser;
use Addiks\PHPSQL\SqlParser\DropSqlParser;
use Addiks\PHPSQL\SqlParser\InsertSqlParser;
use Addiks\PHPSQL\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\SqlParser\SetSqlParser;
use Addiks\PHPSQL\SqlParser\ShowSqlParser;
use Addiks\PHPSQL\SqlParser\UpdateSqlParser;
use Addiks\PHPSQL\SqlParser\UseSqlParser;
use Addiks\PHPSQL\SqlParser\Part\ColumnDefinitionParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\SqlParser\Part\FunctionParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\ParameterConditionParser;
use Addiks\PHPSQL\SqlParser\Part\JoinDefinitionParser;
use Addiks\PHPSQL\SqlParser\Part\ParenthesisParser;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\DatabaseParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\EnumConditionParser;
use Addiks\PHPSQL\SqlParser\Part\Condition\LikeConditionParser;
use Addiks\PHPSQL\SqlParser\Part\FlowControl\CaseParser;

/**
 * This is a parser for SQL statements.
 * You give it an SQL statement in form of a SQL-Token-Iterator,
 * and it either throws an MalformedSqlException exception or returnes an Job-Entity.
 *
 * Technically it acts as a hub for the special parsers (select-parser, insert-parser, create-parser, ...).
 *
 * @see SQLTokenIterator
 * @see Job
 * @see JobRenderer
 */
class SqlParser
{
    
    private $wasInitialized = false;

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return true;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        if (get_class($this) !== __CLASS__) {
            throw new ErrorException("Class '".get_class($this)."' needs to declare an own method '".__FUNCTION__."'!");
        }

        if (!$this->wasInitialized) {
            $this->initSqlSubParsers();
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
                    throw new MalformedSqlException(
                        "Invalid SQL-statement! (Cannot extract command: '{$relevantToken}')",
                        $tokens
                    );
                }
            }
        
        } while ($tokens->isTokenText(';'));
        
        if (!$tokens->isAtEnd() && $tokens->getExclusiveTokenIndex() !== $tokens->getIndex()) {
            throw new MalformedSqlException("Overlapping unparsed SQL at the end of statement!", $tokens);
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

    public function initSqlSubParsers()
    {
        $this->wasInitialized = true;

        ### INSTANCIATE SUB-PARSERS

        // top level parsers (SELECT, UPDATE, CREATE, DELETE, ...)
        $alterParser = new AlterSqlParser();
        $createParser = new CreateSqlParser();
        $deleteParser = new DeleteSqlParser();
        $describeParser = new DescribeSqlParser();
        $dropParser = new DropSqlParser();
        $insertParser = new InsertSqlParser();
        $selectParser = new SelectSqlParser();
        $setParser = new SetSqlParser();
        $showParser = new ShowSqlParser();
        $updateParser = new UpdateSqlParser();
        $useParser = new UseSqlParser();

        // SQL-Part Parsers (Column-Definitions, Joins, Function, Conditions, ...)
        $columnDefinitionParser = new ColumnDefinitionParser();
        $conditionParser = new ConditionParser();
        $functionParser = new FunctionParser();
        $parameterConditionParser = new ParameterConditionParser();
        $joinParser = new JoinDefinitionParser();
        $parenthesisParser = new ParenthesisParser();
        $valueParser = new ValueParser();
        $enumConditionParser = new EnumConditionParser();
        $likeConditionParser = new LikeConditionParser();
        $caseParser = new CaseParser();
        $columnParser = new ColumnParser();
        $databaseParser = new DatabaseParser();
        $tableParser = new TableParser();

        ### CONNECT SUB-PARSERS (RESOLVE DEPENCIES)

        $alterParser->setTableParser($tableParser);
        $alterParser->setColumnParser($columnParser);
        $alterParser->setValueParser($valueParser);
        $alterParser->setColumnDefinitionParser($columnDefinitionParser);
        $createParser->setConditionParser($conditionParser);
        $createParser->setTableParser($tableParser);
        $createParser->setColumnParser($columnParser);
        $createParser->setValueParser($valueParser);
        $createParser->setSelectParser($selectParser);
        $createParser->setColumnDefinitionParser($columnDefinitionParser);
        $deleteParser->setConditionParser($conditionParser);
        $deleteParser->setTableParser($tableParser);
        $deleteParser->setValueParser($valueParser);
        $deleteParser->setJoinParser($joinParser);
        $describeParser->setTableParser($tableParser);
        $dropParser->setValueParser($valueParser);
        $insertParser->setTableParser($tableParser);
        $insertParser->setColumnParser($columnParser);
        $insertParser->setValueParser($valueParser);
        $insertParser->setSelectParser($selectParser);
        $selectParser->setConditionParser($conditionParser);
        $selectParser->setColumnParser($columnParser);
        $selectParser->setTableParser($tableParser);
        $selectParser->setValueParser($valueParser);
        $selectParser->setJoinParser($joinParser);
        $selectParser->setFunctionParser($functionParser);
        $selectParser->setParenthesisParser($parenthesisParser);
        $setParser->setValueParser($valueParser);
        $updateParser->setTableParser($tableParser);
        $updateParser->setColumnParser($columnParser);
        $updateParser->setValueParser($valueParser);
        $useParser->setValueParser($valueParser);
        $columnDefinitionParser->setValueParser($valueParser);
        $conditionParser->setValueParser($valueParser);
        $functionParser->setValueParser($valueParser);
        $functionParser->setSelectParser($selectParser);
        $functionParser->setParameterParser($parameterConditionParser);
        $parameterConditionParser->setValueParser($valueParser);
        $joinParser->setTableParser($tableParser);
        $joinParser->setColumnParser($columnParser);
        $joinParser->setValueParser($valueParser);
        $joinParser->setSelectParser($selectParser);
        $joinParser->setParenthesisParser($parenthesisParser);
        $parenthesisParser->setValueParser($valueParser);
        $parenthesisParser->setSelectParser($selectParser);
        $valueParser->setConditionParser($conditionParser);
        $valueParser->setColumnParser($columnParser);
        $valueParser->setFunctionParser($functionParser);
        $valueParser->setParenthesisParser($parenthesisParser);
        $valueParser->setEnumConditionParser($enumConditionParser);
        $valueParser->setLikeConditionParser($likeConditionParser);
        $valueParser->setCaseParser($caseParser);

        ### REGISTER TOP LEVEL PARSERS

        foreach ([
            $alterParser,
            $createParser,
            $deleteParser,
            $describeParser,
            $dropParser,
            $insertParser,
            $selectParser,
            $setParser,
            $showParser,
            $updateParser,
            $useParser,
        ] as $sqlParser) {
            $this->addSqlParser($sqlParser);
        }

    }
}
