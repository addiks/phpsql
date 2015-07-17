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

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\TokenIterator;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\TableParser;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\Entity\Job\Statement\AlterStatement;
use Addiks\PHPSQL\SqlParser\Part\ColumnDefinitionParser;
use Addiks\PHPSQL\SqlParser\Part\Specifier\ColumnParser;
use Addiks\PHPSQL\Entity\Job\DataChange\AlterTableDataChange;
use Addiks\PHPSQL\Value\Enum\Sql\Alter\DataChange\AlterAttributeType;

class AlterSqlParser extends SqlParser
{
    
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

    protected $columnParser;

    public function getColumnParser()
    {
        return $this->columnParser;
    }

    public function setColumnParser(ColumnParser $columnParser)
    {
        $this->columnParser = $columnParser;
    }

    protected $columnDefinitionParser;

    public function setColumnDefinitionParser(ColumnDefinitionParser $columnDefinitionParser)
    {
        $this->columnDefinitionParser = $columnDefinitionParser;
    }

    public function getColumnDefinitionParser()
    {
        return $this->columnDefinitionParser;
    }

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_ALTER(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_ALTER(), TokenIterator::NEXT));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {

        $alterJob = new AlterStatement();
        
        $tokens->seekTokenNum(SqlToken::T_ALTER());
        
        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_ALTER()) {
            throw new ErrorException("Tried to parse an ALTER statement when token-iterator does not point to T_ALTER!");
        }
        
        $alterJob->setDoIgnoreErrors($tokens->seekTokenNum(SqlToken::T_IGNORE()));
        
        if (!$tokens->seekTokenNum(SqlToken::T_TABLE())) {
            throw new MalformedSql("Missing TABLE for ALTER statement!", $tokens);
        }
        
        if (!$this->tableParser->canParseTokens($tokens)) {
            throw new MalformedSql("Missing Table-Specifier for ALTER TABLE statement!");
        }
        
        $alterJob->setTable($this->tableParser->convertSqlToJob($tokens));
        
        $dataChange = new AlterTableDataChange();
        
        do {
            switch(true){
                case $tokens->seekTokenNum(SqlToken::T_ADD()):
                    
                    $isInParenthesises = ($tokens->seekTokenText('('));
                    
                    do {
                        switch(true){
                            
                            case $tokens->seekTokenNum(SqlToken::T_COLUMN()):
                                if ($tokens->seekTokenText('(')) {
                                    do {
                                        if (!$this->columnDefinitionParser->canParseTokens($tokens)) {
                                            throw new MalformedSql("Missing column definition after ALTER TABLE ADD COLUMN!", $tokens);
                                        }
                                        $dataChange->setAttribute(AlterAttributeType::ADD());
                                        $dataChange->setSubjectColumnDefinition($this->columnDefinitionParser->convertSqlToJob($tokens));
                                        $alterJob->addDataChange(clone $dataChange);
                                    } while ($tokens->seekTokenText(','));
                                    if (!$tokens->seekTokenText(')')) {
                                        throw new MalformedSql("Missing ending parenthesis after column list", $tokens);
                                    }
                                } else {
                                    if (!$this->columnDefinitionParser->canParseTokens($tokens)) {
                                        throw new MalformedSql("Missing column definition after ALTER TABLE ADD COLUMN!", $tokens);
                                    }
                                    $dataChange->setAttribute(AlterAttributeType::ADD());
                                    $dataChange->setSubjectColumnDefinition($this->columnDefinitionParser->convertSqlToJob($tokens));
                                    $alterJob->addDataChange(clone $dataChange);
                                }
                                break;
                            
                            case $this->columnDefinitionParser->canParseTokens($tokens):
                                $dataChange->setAttribute(AlterAttributeType::ADD());
                                $dataChange->setSubjectColumnDefinition($this->columnDefinitionParser->convertSqlToJob($tokens));
                                $alterJob->addDataChange(clone $dataChange);
                                break;
                                
                            case $tokens->seekTokenNum(SqlToken::T_PRIMARY(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                            case $tokens->seekTokenNum(SqlToken::T_UNIQUE(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                            case $tokens->seekTokenNum(SqlToken::T_FOREIGN(), TokenIterator::NEXT, [SqlToken::T_CONSTRAINT(), T_STRING]):
                            case $tokens->seekTokenNum(SqlToken::T_FULLTEXT()):
                            case $tokens->seekTokenNum(SqlToken::T_SPATIAL()):
                            case $tokens->seekTokenNum(SqlToken::T_INDEX()):
                                
                                $indexJob = new IndexJob();
                                
                                if ($tokens->isTokenNum(SqlToken::T_CONSTRAINT(), TokenIterator::PREVIOUS)) {
                                    $beforeIndex = $tokens->getIndex();
                                    if (!$tokens->seekTokenNum(T_STRING, TokenIterator::PREVIOUS)) {
                                        throw new MalformedSql("Missing constraing-symbol T_STRING after T_CONSTRAINT!", $tokens);
                                    }
                                    $indexJob->setContraintSymbol($tokens->getCurrentTokenString());
                                    $tokens->seekIndex($beforeIndex);
                                }
                                
                                $needsReferenceDefinition = false;
                                switch($tokens->getCurrentTokenNumber()){
                                    case SqlToken::T_PRIMARY():
                                        $indexJob->setIsPrimary(true);
                                        $indexJob->setName("PRIMARY");
                                        if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                                            throw new MalformedSql("Missing T_KEY after T_FOREIGN!", $tokens);
                                        }
                                        break;
                                        
                                    case SqlToken::T_UNIQUE():
                                        $indexJob->setIsUnique(true);
                                        $tokens->seekTokenNum(SqlToken::T_INDEX());
                                        break;
                                        
                                    case SqlToken::T_FOREIGN():
                                        if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                                            throw new MalformedSql("Missing T_KEY after T_FOREIGN!", $tokens);
                                        }
                                        $needsReferenceDefinition = true;
                                        break;
                                        
                                    case SqlToken::T_FULLTEXT():
                                        $indexJob->setIsFullText(true);
                                        break;
                                        
                                    case SqlToken::T_SPATIAL():
                                        $indexJob->setIsSpatial(true);
                                        break;
                                }
                                
                                if (!$indexJob->getIsPrimary() && $tokens->seekTokenNum(T_STRING)) {
                                    $indexJob->setName($tokens->getCurrentTokenString());
                                }
                                if ($tokens->seekTokenNum(T_STRING)) {
                                    $indexJob->setType(IndexType::factory(strtoupper($tokens->getCurrentTokenString())));
                                }
                                if (!$tokens->seekTokenText('(')) {
                                    throw new MalformedSql("Missing beginning parenthesis for defining columns for PRIMARY KEY index!", $tokens);
                                }
                                do {
                                    if (!$this->columnParser->canParseTokens($tokens)) {
                                        throw new MalformedSql("Invalid column-specifier in defining columns for PRIMARY KEY index!", $tokens);
                                    }
                                    $indexJob->addColumn($this->columnParser->convertSqlToJob($tokens));
                                } while ($tokens->seekTokenText(','));
                                if (!$tokens->seekTokenText(')')) {
                                    throw new MalformedSql("Missing ending parenthesis for defining columns for PRIMARY KEY index!", $tokens);
                                }
                                
                                if ($needsReferenceDefinition) {
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
                                            $indexJob->addForeignKey(Column::factory("{$fkTable}.{$fkColumn->getColumn()}"));
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
                                                        $indexJob->setForeignKeyOnDeleteReferenceOption(ReferenceOption::RESTRICT());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_CASCADE()):
                                                        $indexJob->setForeignKeyOnDeleteReferenceOption(ReferenceOption::CASCADE());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_SET()) && $tokens->seekTokenNum(SqlToken::T_NULL()):
                                                        $indexJob->setForeignKeyOnDeleteReferenceOption(ReferenceOption::SET_NULL());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_NO()) && $tokens->seekTokenText('ACTION'):
                                                        $indexJob->setForeignKeyOnDeleteReferenceOption(ReferenceOption::NO_ACTION());
                                                        break;
                                                    default:
                                                        throw new MalformedSql("Invalid reference-option for foreign key ON DELETE option!", $tokens);
                                                }
                                                break;
                                            case $tokens->seekTokenNum(SqlToken::T_UPDATE()):
                                                switch(true){
                                                    case $tokens->seekTokenNum(SqlToken::T_RESTRICT()):
                                                        $indexJob->setForeignKeyOnUpdateReferenceOption(ReferenceOption::RESTRICT());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_CASCADE()):
                                                        $indexJob->setForeignKeyOnUpdateReferenceOption(ReferenceOption::CASCADE());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_SET()) && $tokens->seekTokenNum(SqlToken::T_NULL()):
                                                        $indexJob->setForeignKeyOnUpdateReferenceOption(ReferenceOption::SET_NULL());
                                                        break;
                                                    case $tokens->seekTokenNum(SqlToken::T_NO()) && $tokens->seekTokenText('ACTION'):
                                                        $indexJob->setForeignKeyOnUpdateReferenceOption(ReferenceOption::NO_ACTION());
                                                        break;
                                                    default:
                                                        throw new MalformedSql("Invalid reference-option for foreign key ON UPDATE option!", $tokens);
                                                }
                                                break;
                                            default:
                                                throw new MalformedSql("Invalid ON event for foreign key (allowed are UPDATE and DELETE)!", $tokens);
                                        }
                                    }
                                }
                                
                                $dataChange->setAttribute(AlterAttributeType::ADD());
                                $dataChange->setSubjectIndex($indexJob);
                                $alterJob->addDataChange(clone $dataChange);
                                break;
                                
                        }
                    } while ($isInParenthesises && $tokens->seekTokenText(','));
                    
                    if ($isInParenthesises && !$tokens->seekTokenText(')')) {
                        throw new MalformedSql("Missing closing parenthesis after ALTER ADD statement!", $tokens);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_ALTER()):
                    $tokens->seekTokenNum(SqlToken::T_COLUMN());
                    if (!$this->columnParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing column-specification for ALTER COLUMN statement!", $tokens);
                    }
                    $dataChange->setAttribute(AlterAttributeType::DEFAULT_VALUE());
                    $dataChange->setSubject($this->columnParser->convertSqlToJob($tokens));
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_SET()):
                            if (!$tokens->seekTokenNum(SqlToken::T_DEFAULT())) {
                                throw new MalformedSql("Missing T_DEFAULT for ALTER TABLE ALTER COLUMN SET DEFAULT statement", $tokens);
                            }
                            if (!$this->valueParser->canParseTokens($tokens)) {
                                throw new MalformedSql("Missing new valid value for DEFAULT value!");
                            }
                            $dataChange->setValue($this->valueParser->convertSqlToJob($tokens));
                            break;
                        case $tokens->seekTokenNum(SqlToken::T_DROP()):
                            if (!$tokens->seekTokenNum(SqlToken::T_DEFAULT())) {
                                throw new MalformedSql("Missing T_DEFAULT for ALTER TABLE ALTER COLUMN SET DEFAULT statement", $tokens);
                            }
                            $dataChange->setValue(null);
                            break;
                        default:
                            throw new MalformedSql("Invalid action (SET or DROP) for ALTER TABLE ALTER COLUMN statement!", $tokens);
                    }
                    $alterJob->addDataChange(clone $dataChange);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CHANGE()):
                    $dataChange->setAttribute(AlterAttributeType::MODIFY());
                    $tokens->seekTokenNum(SqlToken::T_COLUMN());
                    if (!$this->columnParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing column-specification for ALTER TABLE CHANGE COLUMN statement!", $tokens);
                    }
                    $dataChange->setSubject($this->columnParser->convertSqlToJob($tokens));
                    if (!$this->columnDefinitionParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing valid column-definiton for ALTER TABLE CHANGE COLUMN statement!", $tokens);
                    }
                    $dataChange->setValue($this->columnDefinitionParser->convertSqlToJob($tokens));
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_FIRST()):
                            $dataChange->setAttribute(AlterAttributeType::SET_FIRST());
                            break;
                        case $tokens->seekTokenNum(SqlToken::T_AFTER()):
                            $dataChange->setAttribute(AlterAttributeType::SET_AFTER());
                            if (!$this->columnParser->canParseTokens($tokens)) {
                                throw new MalformedSql("Missing column specifier for ALTER TABLE CHANGE COLUMN AFTER statement!", $tokens);
                            }
                            $dataChange->setValue($this->columnParser->convertSqlToJob($tokens));
                            break;
                    }
                    $alterJob->addDataChange(clone $dataChange);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_MODIFY()):
                    $alterJob->setAction(Action::MODIFY());
                    $tokens->seekTokenNum(SqlToken::T_COLUMN());
                    if (!$this->columnDefinitionParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing valid column definition for ALTER TABLE MODIFY COLUMN statement!", $tokens);
                    }
                    $alterJob->addSubjectColumnDefinition($this->columnDefinitionParser->convertSqlToJob($tokens));
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_FIRST()):
                            $dataChange->setAttribute(AlterAttributeType::SET_FIRST());
                            break;
                        case $tokens->seekTokenNum(SqlToken::T_AFTER()):
                            $dataChange->setAttribute(AlterAttributeType::SET_AFTER());
                            if (!$this->columnParser->canParseTokens($tokens)) {
                                throw new MalformedSql("Missing column specifier for ALTER TABLE MODIFY COLUMN AFTER statement!", $tokens);
                            }
                            $dataChange->setValue($this->columnParser->convertSqlToJob($tokens));
                            break;
                        default:
                            throw new MalformedSql("Invalid parameter for ALTER TABLE MODIFY COLUMN statement! (allowed are FIRST or AFTER)", $tokens);
                    }
                    $alterJob->addDataChange(clone $dataChange);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_DROP()):
                    $dataChange->setAttribute(AlterAttributeType::DROP());
                    switch(true){
                        
                        case $tokens->seekTokenNum(SqlToken::T_COLUMN()):
                            if (!$this->columnParser->canParseTokens($tokens)) {
                                throw new MalformedSql("Missing valid column specificator for ALTER TABLE DROP COLUMN statement!", $tokens);
                            }
                            $dataChange->setSubject($this->columnParser->convertSqlToJob($tokens));
                            break;
                            
                        case $this->columnParser->canParseTokens($tokens):
                            $dataChange->setSubject($this->columnParser->convertSqlToJob($tokens));
                            break;
                        
                        case $tokens->seekTokenNum(SqlToken::T_PRIMARY()):
                            if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                                throw new MalformedSql("Missing T_KEY after T_PRIMARY for ALTER TABLE DROP PRIMARY KEY statement!");
                            }
                            $dataChange->setSubject(Index::factory("PRIMARY"));
                            break;
                            
                        case $tokens->seekTokenNum(SqlToken::T_FOREIGN()):
                            if (!$tokens->seekTokenNum(SqlToken::T_KEY())) {
                                throw new MalformedSql("Missing T_KEY after T_FOREIGN for ALTER TABLE DROP FOREIGN KEY statement!", $tokens);
                            }
                            
                        case $tokens->seekTokenNum(SqlToken::T_INDEX()):
                            if (!$tokens->seekTokenNum(T_STRING)) {
                                throw new MalformedSql("Missing index name for ALTER TABLE DROP INDEX statement!", $tokens);
                            }
                            $dataChange->setSubject(Index::factory($tokens->getCurrentTokenString()));
                            break;
                    }
                    $alterJob->addDataChange(clone $dataChange);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_DISABLE()):
                    $alterJob->setAction(Action::DISABLE());
                    if (!$tokens->seekTokenText(SqlToken::T_KEYS())) {
                        throw new MalformedSql("Missing T_KEYS after T_DISABLE!", $tokens);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_ENABLE()):
                    $alterJob->setAction(Action::ENABLE());
                    if (!$tokens->seekTokenText(SqlToken::T_KEYS())) {
                        throw new MalformedSql("Missing T_KEYS after T_DISABLE!", $tokens);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_RENAME()):
                    if (!$tokens->seekTokenNum(SqlToken::T_TO())) {
                        throw new MalformedSql("Missing T_TO after T_RENAME for ALTER TABLE RENAME TO statement!", $tokens);
                    }
                    if (!$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing new table-name for ALTER TABLE RENAME TO statement!", $tokens);
                    }
                    $dataChange->setAttribute(AlterAttributeType::RENAME());
                    $dataChange->setValue($tokens->getCurrentTokenString());
                        $alterJob->addDataChange(clone $dataChange);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_ORDER()):
                    if (!$tokens->seekTokenNum(SqlToken::T_BY())) {
                        throw new MalformedSql("Missing BY after ORDER in ALTER TABLE ORDER BY statement!", $tokens);
                    }
                    if (!$this->columnParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing column specifier for ALTER TABLE ORDER BY statement!", $tokens);
                    }
                    $dataChange->setSubject($this->columnParser->convertSqlToJob($tokens));
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_DESC()):
                            $dataChange->setAttribute(AlterAttributeType::ORDER_BY_DESC());
                            break;
                            
                        default:
                        case $tokens->seekTokenNum(SqlToken::T_ASC()):
                            $dataChange->setAttribute(AlterAttributeType::ORDER_BY_ASC());
                            break;
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CHARACTER(), TokenIterator::NEXT, [SqlToken::T_DEFAULT()]):
                case $tokens->seekTokenNum(SqlToken::T_CHARACTER(), TokenIterator::NEXT, [SqlToken::T_CONVERT(), SqlToken::T_TO()]):
                    if (!$tokens->seekTokenNum(SqlToken::T_SET())) {
                        throw new MalformedSql("Missing T_SET after T_CHARACTER for ALTER TABLE CONVERT TO CHARACTER SET statement!", $tokens);
                    }
                    if (!$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing character-set specifier for ALTER TABLE CONVERT TO CHARACTER SET statement!", $tokens);
                    }
                    $dataChange->setAttribute(AlterAttributeType::CHARACTER_SET());
                    $dataChange->setValue($tokens->getCurrentTokenString());
                    $alterJob->addDataChange(clone $dataChange);
                    if ($tokens->seekTokenNum(SqlToken::T_COLLATE())) {
                        if (!$tokens->seekTokenNum(T_STRING)) {
                            throw new MalformedSql("Missing collation-specifier for ALTER TABLE CONVERT TO CHARACTER SET COLLATE statement!", $tokens);
                        }
                        $dataChange->setAttribute(AlterAttributeType::COLLATE());
                        $dataChange->setValue($tokens->getCurrentTokenString());
                        $alterJob->addDataChange(clone $dataChange);
                    }
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_DISCARD()):
                case $tokens->seekTokenNum(SqlToken::T_IMPORT()):
                    if (!$tokens->seekTokenNum(SqlToken::T_TABLESPACE())) {
                        throw new MalformedSql("Missing T_TABLESPACE after T_DISCARD or T_IMPORT!", $tokens);
                    }
                    break;
            }
            
        } while ($tokens->seekTokenText(','));
        
        return $alterJob;
    }
}
