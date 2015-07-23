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

use Exception;
use Addiks\PHPSQL\Entity\Job\Statement\Create\CreateDatabaseStatement;
use Addiks\PHPSQL\Entity\Job\Statement\Create\CreateIndexStatement;
use Addiks\PHPSQL\Entity\Job\Statement\Create\CreateTableStatement;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\Value\Enum\Sql\IndexType;
use Addiks\PHPSQL\Entity\Job\Part\Index as IndexPart;
use Addiks\PHPSQL\SqlParser\Part\Condition;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\SqlParser\Part\ColumnDefinition;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Select;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\SqlParser\Part\ColumnDefinitionParser;
use Addiks\PHPSQL\SqlParser\Part\ConditionParser;
use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier;
use Addiks\PHPSQL\Value\Enum\Sql\ForeignKey\ReferenceOption;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;

class CreateSqlParser extends SqlParser
{
    
    protected $conditionParser;

    public function getConditionParser()
    {
        return $this->conditionParser;
    }

    public function setConditionParser(ConditionParser $conditionParser)
    {
        $this->conditionParser = $conditionParser;
    }

    protected $tableParser;

    public function getTableParser()
    {
        return $this->tableParser;
    }

    public function setTableParser(TableParser $tableParser)
    {
        $this->tableParser = $tableParser;
    }

    protected $valueParser;

    public function getValueParser()
    {
        return $this->valueParser;
    }

    public function setValueParser(ValueParser $valueParser)
    {
        $this->valueParser = $valueParser;
    }

    protected $selectParser;

    public function getSelectParser()
    {
        return $this->selectParser;
    }

    public function setSelectParser(SelectSqlParser $selectParser)
    {
        $this->selectParser = $selectParser;
    }

    protected $columnParser;

    public function getColumnParser()
    {
        return $this->columnParser;
    }

    public function setColumnParser(ColumnParser $columnParser)
    {
        $this->columnParser = $columnParser;
    }

    protected $columnDefinitonParser;

    public function getColumnDefinitionParser()
    {
        return $this->columnDefinitonParser;
    }

    public function setColumnDefinitionParser(ColumnDefinitionParser $columnDefinitonParser)
    {
        $this->columnDefinitonParser = $columnDefinitonParser;
    }

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_CREATE(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_CREATE(), TokenIterator::NEXT));
    }
    
    /**
     * @return Create
     * @see Addiks\PHPSQL.SqlParser::convertSqlToJob()
     */
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {

        $tokens->seekTokenNum(SqlToken::T_CREATE());
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_CREATE()) {
            throw new ErrorException("Tried to parse create-statement when token-iterator is not at T_CREATE!");
        }
        
        switch(true){
            
            case $tokens->seekTokenNum(SqlToken::T_SCHEMA(), TokenIterator::NEXT):
            case $tokens->seekTokenNum(SqlToken::T_DATABASE()):
                return $this->parseCreateDatabase($tokens);
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_TABLE(), TokenIterator::NEXT, [SqlToken::T_TEMPORARY()]):
                return $this->parseCreateTable($tokens);
                break;
                
            case $tokens->seekTokenNum(SqlToken::T_INDEX(), TokenIterator::NEXT, [SqlToken::T_UNIQUE(), SqlToken::T_PRIMARY()]):
                return $this->parseCreateIndex($tokens);
                break;
                
            default:
                throw new MalformedSql("Invalid type of create-statement!", $tokens);
        }
    }
    
    protected function parseCreateDatabase(SQLTokenIterator $tokens)
    {
        
        if ($tokens->seekTokenNum(SqlToken::T_IF())) {
            if (!$tokens->seekTokenNum(SqlToken::T_NOT())
            || !$tokens->seekTokenNum(SqlToken::T_EXISTS())) {
                throw new MalformedSql("Invalid create-database statement (invalid 'IF NOT EXISTS')!", $tokens);
            }
            $ifNotExist = true;
        } else {
            $ifNotExist = false;
        }
        
        if (!$this->valueParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing name of database to create!", $tokens);
        }
        
        $databaseName = $this->valueParser->convertSqlToJob($tokens);
        
        $createJob = new CreateDatabaseStatement();
        $createJob->setIfNotExists($ifNotExist);
        $createJob->setName($databaseName);
        
        return $createJob;
    }
    
    /**
     * This parses a CREATE TABLE statement.
     *
     * @param SQLTokenIterator $tokens
     * @throws MalformedSql
     */
    protected function parseCreateTable(SQLTokenIterator $tokens)
    {
        
        $createTableJob = new CreateTableStatement();
        $createTableJob->setIsTemporaryTable(is_int($tokens->isTokenNum(SqlToken::T_TEMPORARY(), TokenIterator::PREVIOUS)));
        
        # [IF NOT EXISTS]:
        if ($tokens->seekTokenNum(SqlToken::T_IF())) {
            if (!$tokens->seekTokenNum(SqlToken::T_NOT())
            || !$tokens->seekTokenNum(SqlToken::T_EXISTS())) {
                throw new MalformedSql("Invalid create-database statement (invalid 'IF NOT EXISTS')!", $tokens);
            }
            $createTableJob->setIfNotExists(true);
        } else {
            $createTableJob->setIfNotExists(false);
        }
        
        # NAME
        
        if (!$tokens->seekTokenNum(T_STRING)) {
            throw new MalformedSql("Missing name of table to create!", $tokens);
        }
        
        $createTableJob->setName($tokens->getCurrentTokenString());
        
        # COLUMN DEFINITION
        
        $checkEndParenthesis = $tokens->seekTokenText('(');
        
        # LIKE other table?
        if ($tokens->seekTokenNum(SqlToken::T_LIKE())) {
            if (!$this->tableParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid table-specifier for 'CREATE TABLE LIKE' statement!", $tokens);
            }
            $createTableJob->setLikeTable($this->tableParser->convertSqlToJob($tokens));
            
        } elseif ($this->selectParser->canParseTokens($tokens)) {
            $createTableJob->setFromSelectStatement($this->selectParser->convertSqlToJob($tokens));
            
        # normal column definition
        } else {
            do {
                switch(true){
                    
                    # normal column definition
                    case $this->columnDefinitonParser->canParseTokens($tokens):
                        $createTableJob->addColumnDefinition($this->columnDefinitonParser->convertSqlToJob($tokens));
                        break;
                        
                    # [CONSTRAINT [$keyName]] PRIMARY KEY [$keyType] ($column[, $column, ...])
                    case $tokens->seekTokenNum(SqlToken::T_PRIMARY(), TokenIterator::NEXT, [T_STRING, SqlToken::T_CONSTRAINT()]):
                            
                        $indexJob = new IndexPart();
                        $indexJob->setIsPrimary(true);
                        
                        if ($tokens->isTokenNum(SqlToken::T_CONSTRAINT(), TokenIterator::PREVIOUS, [T_STRING])
                        && $tokens->seekTokenNum(T_STRING, TokenIterator::PREVIOUS)) {
                            $indexJob->setContraintSymbol($tokens->getPreviousTokenString());
                            $tokens->seekTokenNum(SqlToken::T_PRIMARY());
                        }
                        
                        $indexJob->setName("PRIMARY");
                        
                        if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                            throw new MalformedSql("Missing T_KEY for PRIMARY KEY constraint in create-table statement!", $tokens);
                        }
                        
                        # define index type (BTREE, HASH, ...)
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setType(IndexType::factory($tokens->getCurrentTokenString()));
                        } else {
                            $indexJob->setType(IndexType::BTREE());
                        }
                        
                        # columns in index
                        if ($tokens->seekTokenText('(')) {
                            do {
                                if (!$this->columnParser->canParseTokens($tokens)) {
                                    throw new MalformedSql("Invalid column in column-list for defining index!", $tokens);
                                }
                                $indexJob->addColumn($this->columnParser->convertSqlToJob($tokens));
                            } while ($tokens->seekTokenText(','));
                            
                            if (!$tokens->seekTokenText(')')) {
                                throw new MalformedSql("Missing closing parenthesis at column-list for index!", $tokens);
                            }
                        }
                        
                        $createTableJob->addIndex($indexJob);
                        break;
                        
                    # KEY|INDEX [index_name] [index_type] (index_col_name,...)
                    case $tokens->seekTokenNum(SqlToken::T_INDEX()):
                    case $tokens->seekTokenNUm(SqlToken::T_KEY()):
                        /* @var $indexJob IndexPart */
                        $indexJob = new IndexPart();
                        
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setName($tokens->getCurrentTokenString());
                        } else {
                            $indexJob->setName(null); # first column name is used
                        }
                        
                        # define index type (BTREE, HASH, ...)
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setType(IndexType::factory($tokens->getCurrentTokenString()));
                        } else {
                            $indexJob->setType(IndexType::BTREE());
                        }
                        
                        # columns in index
                        if ($tokens->seekTokenText('(')) {
                            do {
                                if (!$this->columnParser->canParseTokens($tokens)) {
                                    throw new MalformedSql("Invalid column in column-list for defining index!", $tokens);
                                }
                                $indexJob->addColumn($this->columnParser->convertSqlToJob($tokens));
                            } while ($tokens->seekTokenText(','));
                        
                            if (!$tokens->seekTokenText(')')) {
                                throw new MalformedSql("Missing closing parenthesis at column-list for index!", $tokens);
                            }
                        }
                        
                        $createTableJob->addIndex($indexJob);
                        break;
                        
                    # [CONSTRAINT [symbol]] UNIQUE|FULLTEXT|SPATIAL [INDEX] [index_name] [index_type] (index_col_name,...)
                    case $tokens->seekTokenNum(SqlToken::T_UNIQUE(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                    case $tokens->seekTokenNum(SqlToken::T_FULLTEXT(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                    case $tokens->seekTokenNum(SqlToken::T_SPATIAL(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                            
                        /* @var $indexJob Index */
                        $indexJob = new Index();
                            
                        switch($tokens->getCurrentTokenNumber()){
                            case SqlToken::T_UNIQUE():
                                $indexJob->setIsUnique(true);
                                break;
                        
                            case SqlToken::T_FULLTEXT():
                                $indexJob->setIsFullText(true);
                                break;
                        
                            case SqlToken::T_SPATIAL():
                                $indexJob->setIsSpatial(true);
                                break;
                        }
                        
                        if ($tokens->isTokenNum(SqlToken::T_CONSTRAINT(), TokenIterator::PREVIOUS, [T_STRING])
                        && $tokens->seekTokenNum(T_STRING, TokenIterator::PREVIOUS)) {
                            $indexJob->setContraintSymbol($tokens->getPreviousTokenString());
                            $tokens->seekTokenNum(SqlToken::T_PRIMARY());
                        }
                        
                        $tokens->seekTokenNum(SqlToken::T_KEY());
                        $tokens->seekTokenNum(SqlToken::T_INDEX());
                        
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setName($tokens->getCurrentTokenString());
                        } else {
                            $indexJob->setName(null); # first column name is used
                        }
                        
                        # define index type (BTREE, HASH, ...)
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setType(IndexType::factory($tokens->getCurrentTokenString()));
                        } else {
                            $indexJob->setType(IndexType::BTREE());
                        }
                        
                        # columns in index
                        if ($tokens->seekTokenText('(')) {
                            do {
                                if (!$this->columnParser->canParseTokens($tokens)) {
                                    throw new MalformedSql("Invalid column in column-list for defining index!", $tokens);
                                }
                                $indexJob->addColumn($this->columnParser->convertSqlToJob($tokens));
                            } while ($tokens->seekTokenText(','));
                        
                            if (!$tokens->seekTokenText(')')) {
                                throw new MalformedSql("Missing closing parenthesis at column-list for index!", $tokens);
                            }
                        }
                        
                        $createTableJob->addIndex($indexJob);
                        break;
                        
                    # [CONSTRAINT [$symbol]] FOREIGN KEY [$name] ($column[, $column, ...]) [$reference]
                    case $tokens->seekTokenNum(SqlToken::T_FOREIGN(), TokenIterator::NEXT, [T_STRING, SqlToken::T_CONSTRAINT()]):
                        /* @var $indexJob IndexPart */
                        $indexJob = new IndexPart();
                        
                        if ($tokens->isTokenNum(SqlToken::T_CONSTRAINT(), TokenIterator::PREVIOUS, [T_STRING])
                        && $tokens->seekTokenNum(T_STRING, TokenIterator::PREVIOUS)) {
                            $indexJob->setContraintSymbol($tokens->getCurrentTokenString());
                            $tokens->seekTokenNum(SqlToken::T_FOREIGN());
                        }
                        
                        if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                            throw new MalformedSql("Missing T_KEY after T_FOREIGN in constraint-definition!", $tokens);
                        }
                        
                        if ($tokens->seekTokenNum(T_STRING)) {
                            $indexJob->setName($tokens->getCurrentTokenString());
                        } else {
                            $indexJob->setName(null); # first column name is used
                        }
                        
                        # columns in index
                        if ($tokens->seekTokenText('(')) {
                            do {
                                if (!$this->columnParser->canParseTokens($tokens)) {
                                    throw new MalformedSql("Invalid column in column-list for defining index!", $tokens);
                                }
                                $indexJob->addColumn($this->columnParser->convertSqlToJob($tokens));
                            } while ($tokens->seekTokenText(','));
                                
                            if (!$tokens->seekTokenText(')')) {
                                throw new MalformedSql("Missing closing parenthesis at column-list for index!", $tokens);
                            }
                        }
                        
                        if (!$tokens->seekTokenNum(SqlToken::T_REFERENCES())) {
                            throw new MalformedSql("Missing reference-definition in foreign-constraint-definition!", $tokens);
                        }
                        
                        if (!$this->tableParser->canParseTokens($tokens)) {
                            throw new MalformedSql("Missing table-definition in foreign-constraint-definition!", $tokens);
                        }
                        $fkTable = $this->tableParser->convertSqlToJob($tokens);
                        
                        # columns in index
                        if ($tokens->seekTokenText('(')) {
                            do {
                                if (!$this->columnParser->canParseTokens($tokens)) {
                                    throw new MalformedSql("Invalid column in column-list for defining index!", $tokens);
                                }
                                $fkColumn = $this->columnParser->convertSqlToJob($tokens);
                                $fkColumn = ColumnSpecifier::factory($fkTable.'.'.$fkColumn->getColumn());
                                $indexJob->addForeignKey($fkColumn);
                            } while ($tokens->seekTokenText(','));
                                
                            if (!$tokens->seekTokenText(')')) {
                                throw new MalformedSql("Missing closing parenthesis at column-list for index!", $tokens);
                            }
                        }
                            
                        if ($tokens->seekTokenNum(SqlToken::T_MATCH())) {
                            switch(true){
                                case $tokens->seekTokenNum(SqlToken::T_FULL()):
                                    $indexJob->setForeignKeyMatchType(MatchType::FULL());
                                    break;
                                case $tokens->seekTokenNum(SqlToken::T_PARTIAL()):
                                    $indexJob->setForeignKeyMatchType(MatchType::PARTIAL());
                                    break;
                                case $tokens->seekTokenNum(SqlToken::T_SIMPLE()):
                                    $indexJob->setForeignKeyMatchType(MatchType::SIMPLE());
                                    break;
                                default:
                                    throw new MalformedSql("Invalid match parameter for foreign key!", $tokens);
                            }
                        }
                        
                        while ($tokens->seekTokenNum(SqlToken::T_ON())) {
                            switch(true){

                                case $tokens->seekTokenNum(SqlToken::T_DELETE()):
                                    switch(true){

                                        case $tokens->seekTokenNum(SqlToken::T_RESTRICT()):
                                            $option = ReferenceOption::RESTRICT();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_CASCADE()):
                                            $option = ReferenceOption::CASCADE();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_SET())
                                          && $tokens->seekTokenNum(SqlToken::T_NULL()):
                                            $option = ReferenceOption::SET_NULL();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_NO())
                                          && $tokens->seekTokenText("ACTION"):
                                            $option = ReferenceOption::NO_ACTION();
                                            break;

                                        default:
                                            throw new MalformedSql(
                                                "Invalid reference-option for foreign key ON DELETE option!",
                                                $tokens
                                            );
                                    }
                                    $indexJob->setForeignKeyOnDeleteReferenceOption($option);
                                    break;

                                case $tokens->seekTokenNum(SqlToken::T_UPDATE()):
                                    switch(true){

                                        case $tokens->seekTokenNum(SqlToken::T_RESTRICT()):
                                            $option = ReferenceOption::RESTRICT();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_CASCADE()):
                                            $option = ReferenceOption::CASCADE();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_SET())
                                          && $tokens->seekTokenNum(SqlToken::T_NULL()):
                                            $option = ReferenceOption::SET_NULL();
                                            break;

                                        case $tokens->seekTokenNum(SqlToken::T_NO())
                                          && $tokens->seekTokenText("ACTION"):
                                            $option = ReferenceOption::NO_ACTION();
                                            break;

                                        default:
                                            throw new MalformedSql(
                                                "Invalid reference-option for foreign key ON UPDATE option!",
                                                $tokens
                                            );
                                    }
                                    $indexJob->setForeignKeyOnUpdateReferenceOption($option);
                                    break;

                                default:
                                    throw new MalformedSql("Invalid ON event for foreign key (allowed are UPDATE and DELETE)!", $tokens);
                            }
                        }
                        
                        $createTableJob->addIndex($indexJob);
                        break;
                        
                    # CHECK (expression)
                    case $tokens->seekTokenNum(SqlToken::T_CHECK()):
                        if (!$this->conditionParser->canParseTokens($tokens)) {
                            throw new MalformedSql("Invalid CHECK condition statement!", $tokens);
                        }
                        $createTableJob->addCheck($this->conditionParser->convertSqlToJob($tokens));
                        break;
                    
                    default:
                        throw new MalformedSql("Invalid definition in CREATE TABLE statement!", $tokens);
                }
            } while ($tokens->seekTokenText(','));
        }
            
        if ($checkEndParenthesis && !$tokens->seekTokenText(')')) {
            throw new MalformedSql("Missing closing parenthesis at end of table-definition!", $tokens);
        }
            
        ### TABLE OPTIONS
        
        while (true) {
            switch(true){
                
                case $tokens->seekTokenNum(SqlToken::T_ENGINE()):
                case $tokens->seekTokenNum(SqlToken::T_TYPE()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing T_STRING after T_ENGINE!", $tokens);
                    }
                    $createTableJob->setEngine(Engine::factory($tokens->getCurrentTokenString()));
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_AUTO_INCREMENT()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing number-string for T_AUTO_INCREMENT!", $tokens);
                    }
                    $createTableJob->setAutoIncrement((int)$tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_AVG_ROW_LENGTH()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing number-string for T_AVG_ROW_LENGTH!", $tokens);
                    }
                    $createTableJob->setAverageRowLength((int)$tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CHARACTER(), TokenIterator::NEXT, [SqlToken::T_DEFAULT()]):
                    if (!$tokens->seekTokenNum(SqlToken::T_SET())) {
                        throw new MalformedSql("Missing SET after CHARACTER keyword!", $tokens);
                    }
                case $tokens->seekTokenNum(SqlToken::T_CHARSET(), TokenIterator::NEXT, [SqlToken::T_DEFAULT()]):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING) && !$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing string for CHARACTER SET!", $tokens);
                    }
                    $createTableJob->setCharacterSet($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_COLLATE()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING) && !$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing string for COLLATE!", $tokens);
                    }
                    $createTableJob->setCollate($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CHECKSUM()):
                    $tokens->seekTokenText('=');
                    switch(true){
                        case $tokens->seekTokenText('0'):
                            $createTableJob->setUseChecksum(false);
                            break;
                        case $tokens->seekTokenText('1'):
                            $createTableJob->setUseChecksum(true);
                            break;
                        default:
                            throw new MalformedSql("Invalid value for CHECKSUM! (only 0 or 1 allowed!)", $tokens);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_COMMENT()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for comment!", $tokens);
                    }
                    $createTableJob->setComment($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CONNECTION()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for connection-string!", $tokens);
                    }
                    $createTableJob->setConnectString($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_MAX_ROWS()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing number-string for MAX_ROWS!", $tokens);
                    }
                    $createTableJob->setMaximumRows((int)$tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_MIN_ROWS()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing number-string for MIN_ROWS!", $tokens);
                    }
                    $createTableJob->setMinimumRows((int)$tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_PACK_KEYS()):
                    $tokens->seekTokenText('=');
                    switch(true){
                        case $tokens->seekTokenText('DEFAULT'):
                        case $tokens->seekTokenText('0'):
                            $createTableJob->setDelayKeyWrite(false);
                            break;
                        case $tokens->seekTokenText('1'):
                            $createTableJob->setDelayKeyWrite(true);
                            break;
                        default:
                            throw new MalformedSql("Invalid value for PACK_KEYS! (only DEFAULT, 0 or 1 allowed!)", $tokens);
                    }
                    break;
                
                case $tokens->seekTokenNum(SqlToken::T_PASSWORD()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for password!", $tokens);
                    }
                    $createTableJob->setPassword($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_DELAY_KEY_WRITE()):
                    $tokens->seekTokenText('=');
                    switch(true){
                        case $tokens->seekTokenText('0'):
                            $createTableJob->setDelayKeyWrite(false);
                            break;
                        case $tokens->seekTokenText('1'):
                            $createTableJob->setDelayKeyWrite(true);
                            break;
                        default:
                            throw new MalformedSql("Invalid value for DELAY_KEY_WRITE! (only 0 or 1 allowed!)", $tokens);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_ROW_FORMAT()):
                    $tokens->seekTokenText('=');
                    $keyword = $tokens->getExclusiveTokenString();
                    $rowFormat = RowFormat::factory($keyword);
                    $tokens->seekIndex($tokens->getExclusiveTokenIndex());
                    $createTableJob->setRowFormat($rowFormat);
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_UNION()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenText('(')) {
                        throw new MalformedSql("Missing opening parenthesis for union-table-definition!", $tokens);
                    }
                    do {
                        if (!$this->tableParser->canParseTokens($tokens)) {
                            throw new MalformedSql("Invalid table in table-list for defining union tables!", $tokens);
                        }
                        $createTableJob->addUnionTable($this->tableParser->convertSqlToJob($tokens));
                    } while ($tokens->seekTokenText(','));
                        
                    if (!$tokens->seekTokenText(')')) {
                        throw new MalformedSql("Missing closing parenthesis at union-table-list!", $tokens);
                    }
                    break;
                
                case $tokens->seekTokenNum(SqlToken::T_INSERT_METHOD()):
                    $tokens->seekTokenText('=');
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_NO()):
                            $createTableJob->setInsertMethod(InsertMethod::NO());
                            break;
                        case $tokens->seekTokenNum(SqlToken::T_FIRST()):
                            $createTableJob->setInsertMethod(InsertMethod::FIRST());
                            break;
                        case $tokens->seekTokenNum(SqlToken::T_LAST()):
                            $createTableJob->setInsertMethod(InsertMethod::LAST());
                            break;
                        default:
                            throw new MalformedSql("Invalid value given for insert-method (allowed are NO, FIRST and LAST)!");
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_DATA()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(SqlToken::T_DIRECTORY())) {
                        throw new MalformedSql("Missing T_DIRECTORY after T_DATA for data-directory!", $tokens);
                    }
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for comment!", $tokens);
                    }
                    $createTableJob->setDataDirectory($tokens->getCurrentTokenString());
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_INDEX()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(SqlToken::T_DIRECTORY())) {
                        throw new MalformedSql("Missing T_DIRECTORY after T_INDEX for index-directory!", $tokens);
                    }
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for comment!", $tokens);
                    }
                    $createTableJob->setIndexDirectory($tokens->getCurrentTokenString());
                    break;
                
                default:
                    break 2;
            }
            
        }
        
        return $createTableJob;
    }
    
    /**
     * This parses a CREATE INDEX statement.
     */
    protected function parseCreateIndex(SQLTokenIterator $tokens)
    {
        
        /* @var $entity CreateIndexStatement */
        $entity = new CreateIndexStatement();
        
        ### FLAGS
        
        if ($tokens->isToken(SqlToken::T_UNIQUE(), TokenIterator::PREVIOUS, [SqlToken::T_PRIMARY()])) {
            $entity->setIsUnique(true);
        }
        
        if ($tokens->isToken(SqlToken::T_PRIMARY(), TokenIterator::PREVIOUS, [SqlToken::T_UNIQUE()])) {
            $entity->setIsPrimary(true);
        }
        
        ### NAME
        
        if (!$tokens->seekTokenNum(T_STRING)) {
            throw new MalformedSql("Missing valid index-name!", $tokens);
        }
        
        $entity->setName($tokens->getCurrentTokenString());
        
        ### USING
        
        if ($tokens->seekTokenNum(SqlToken::T_USING())) {
            switch(true){
                
                // TODO: R-TREE index not implemented yet!
                case $tokens->seekTokenNum(SqlToken::T_RTREE()):
                case $tokens->seekTokenNum(SqlToken::T_BTREE()):
                    $entity->setIndexType(IndexType::BTREE());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_HASH()):
                    $entity->setIndexType(IndexType::HASH());
                    break;
                        
                default:
                    throw new MalformedSql("Invalid index-type specified!", $tokens);
            }
        }
        
        ### TABLE
        
        if (!$tokens->seekTokenNum(SqlToken::T_ON())) {
            throw new MalformedSql("Missing T_ON for CREATE INDEX statement!", $tokens);
        }
        
        if (!$this->tableParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing valid table-specifier for CREATE INDEX statement!", $tokens);
        }
        
        $entity->setTable($this->tableParser->convertSqlToJob($tokens));
        
        ### COLUMNS
        
        if (!$tokens->seekTokenText('(')) {
            throw new MalformedSql("Missing beginning parenthesis holding columns in CREATE INDEX statement!", $tokens);
        }
        
        do {
            if (!$this->columnParser->canParseTokens($tokens)) {
                throw new MalformedSql("Missing valid column-specifier in CREATE INDEX statement!", $tokens);
            }
            
            $column = $this->columnParser->convertSqlToJob($tokens);
            
            $length = null;
            if ($tokens->seekTokenText('(')) {
                if (!$this->valueParser->canParseTokens($tokens)) {
                    throw new MalformedSql("Missing valid column-length in CREATE INDEX statement!", $tokens);
                }
                
                $length = $this->valueParser->convertSqlToJob($tokens);
                
                if (!$tokens->seekTokenText(')')) {
                    throw new MalformedSql("Missing closing parenthesis holding column-length in CREATE INDEX statement!", $tokens);
                }
            }
            
            $direction = null;
            if ($tokens->seekTokenNum(SqlToken::T_ASC())) {
                $direction = SqlToken::T_ASC();
            } elseif ($tokens->seekTokenNum(SqlToken::T_DESC())) {
                $direction = SqlToken::T_DESC();
            }
            
            $entity->addColumn($column, $length, $direction);
            
        } while ($tokens->seekTokenText(','));
        
        if (!$tokens->seekTokenText(')')) {
            throw new MalformedSql("Missing closing parenthesis holding columns in CREATE INDEX statement!", $tokens);
        }
        
        ### WITH PARSER
        
        if ($tokens->seekTokenNum(SqlToken::T_WITH())) {
            if (!$tokens->seekTokenNum(SqlToken::T_PARSER())) {
                throw new MalformedSql("Missing T_PARSER after T_WITH in CREATE INDEX statement!", $tokens);
            }
            
            if (!$tokens->seekTokenNum(T_STRING)) {
                throw new MalformedSql("Missing valid parser name after WITH PARSER in CREATE INDEX statement!", $tokens);
            }
            
            $entity->setParser($tokens->getCurrentTokenString());
        }
        
        return $entity;
    }
}
