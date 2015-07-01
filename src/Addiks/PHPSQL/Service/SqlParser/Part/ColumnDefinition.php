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

namespace Addiks\PHPSQL\Service\SqlParser\Part;

use Addiks\PHPSQL\Entity\Job\Part\ColumnDefinition as ColumnDefinitionJob;

use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

use Addiks\PHPSQL\Service\SqlParser;

use Addiks\PHPSQL\Service\SqlParser\Part\ValueParser;

class ColumnDefinition extends SqlParser
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        $indexBefore = $tokens->getIndex();
        
        $tokens->seekTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING]);
        
        $result = false;
        
        // check if column-name is there
        if ($tokens->isTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING], TokenIterator::CURRENT)) {
            try {
                // check if valid data-type is there
                $dataTypeString = strtoupper($tokens->getExclusiveTokenString());
                $dataType = DataType::factory($dataTypeString);
                
                $result = true;
                
            } catch (\Addiks\Protocol\Entity\Exception\Error $exception) {
                # skips $result=true
            }
        }
        
        $tokens->seekIndex($indexBefore);
        
        return $result;
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        /* @var $valueParser ValueParser */
        $this->factorize($valueParser);
        
        /* @var $columnDefinition ColumnDefinitionJob */
        $this->factorize($columnDefinition);
        
        $tokens->seekTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING]);
        
        if (!$tokens->isTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING], TokenIterator::CURRENT)) {
            throw new MalformedSql("Missing name for column!", $tokens);
        }
        
        $name = $tokens->getCurrentTokenString();
        
        if ($name[0] === '`' && $name[strlen($name)-1] === '`') {
            $name = substr($name, 1, strlen($name)-2);
        }
        if ($name[0] === '"' && $name[strlen($name)-1] === '"') {
            $name = substr($name, 1, strlen($name)-2);
        }
        if ($name[0] === "'" && $name[strlen($name)-1] === "'") {
            $name = substr($name, 1, strlen($name)-2);
        }
        
        $columnDefinition->setName($name);
        
        # this makes sure that the next token is valid data-type
        $dataTypeString = strtoupper($tokens->getExclusiveTokenString());
        $dataType = DataType::factory($dataTypeString);
        $columnDefinition->setDataType($dataType);
        $tokens->seekIndex($tokens->getExclusiveTokenIndex());
        
        # data-type-length
        if ($tokens->seekTokenText('(')) {
            if ($dataType === DataType::ENUM() || $dataType === DataType::SET()) {
                do {
                    if (!$valueParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Invalid value in ENUM!", $tokens);
                    }
                    $columnDefinition->addEnumValue($valueParser->convertSqlToJob($tokens));
                } while ($tokens->seekTokenText(','));
                
            } else {
                if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                    throw new MalformedSql("Missing number for length of data-type!", $tokens);
                }
                $columnDefinition->setDataTypeLength((int)$tokens->getCurrentTokenString());
                    
                if ($tokens->seekTokenText(',')) {
                    if (!$tokens->seekTokenNum(T_NUM_STRING)) {
                        throw new MalformedSql("Missing second number for length of data-type!", $tokens);
                    }
                    $columnDefinition->setDataTypeSecondLength((int)$tokens->getCurrentTokenString());
                }
            }
                    
            if (!$tokens->seekTokenText(')')) {
                throw new MalformedSql("Missing end-parenthesis for length of data-type!", $tokens);
            }
        }
        
        while (true) {
            switch(true){
                case $tokens->seekTokenNum(SqlToken::T_NOT()):
                    if (!$tokens->seekTokenNum(SqlToken::T_NULL())) {
                        throw new MalformedSql("Missing T_NULL after T_NOT in column-definition!", $tokens);
                    }
                    $columnDefinition->setIsNullable(false);
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_NULL()):
                    $columnDefinition->setIsNullable(true);
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_DEFAULT()):
                    if (!$valueParser->canParseTokens($tokens)) {
                        throw new MalformedSql("Missing valid default value for column definition!", $tokens);
                    }
                    $columnDefinition->setDefaultValue($valueParser->convertSqlToJob($tokens));
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_UNIQUE()):
                    $tokens->seekTokenNum(SqlToken::T_KEY());
                    $columnDefinition->setIsUnique(true);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_PRIMARY()):
                    $tokens->seekTokenNum(SqlToken::T_KEY());
                    $columnDefinition->setIsPrimaryKey(true);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_UNSIGNED()):
                    $columnDefinition->setIsUnsigned(true);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_COMMENT()):
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
                        throw new MalformedSql("Missing encapsed string for comment-declaration column-definition!", $tokens);
                    }
                    $columnDefinition->setComment($tokens->getCurrentTokenString());
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_AUTO_INCREMENT()):
                    $columnDefinition->setAutoIncrement(true);
                    break;
                    
                case $tokens->seekTokenNum(SqlToken::T_CHARACTER(), TokenIterator::NEXT, [SqlToken::T_DEFAULT()]):
                    if (!$tokens->seekTokenNum(SqlToken::T_SET())) {
                        throw new MalformedSql("MIssing SET after CHARACTER keyword!", $tokens);
                    }
                case $tokens->seekTokenNum(SqlToken::T_CHARSET(), TokenIterator::NEXT, [SqlToken::T_DEFAULT()]):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING) && !$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing string for CHARACTER SET!", $tokens);
                    }
                    $columnDefinition->setCharacterSet($tokens->getCurrentTokenString());
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_COLLATE()):
                    $tokens->seekTokenText('=');
                    if (!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING) && !$tokens->seekTokenNum(T_STRING)) {
                        throw new MalformedSql("Missing string for COLLATE!", $tokens);
                    }
                    $columnDefinition->setCollate($tokens->getCurrentTokenString());
                    break;
                        
                case $tokens->seekTokenNum(SqlToken::T_ON()):
                    switch(true){
                        case $tokens->seekTokenNum(SqlToken::T_UPDATE()):
                            switch(true){
                                case $valueParser->canParseTokens($tokens):
                                    $columnDefinition->setOnUpdate($valueParser->convertSqlToJob($tokens));
                                    break;
                                
                                default:
                                    throw new MalformedSql("Invalid value for ON UPDATE!", $tokens);
                            }
                            break;
                        
                        case $tokens->seekTokenNum(SqlToken::T_DELETE()):
                            switch(true){
                                case $valueParser->canParseTokens($tokens):
                                    $columnDefinition->setOnDelete($valueParser->convertSqlToJob($tokens));
                                    break;
                                
                                default:
                                    throw new MalformedSql("Invalid value for ON UPDATE!", $tokens);
                            }
                            break;
                            
                        default:
                            throw new MalformedSql("Only UPDATE and DELETE allowed for ON trigger!", $tokens);
                    }
                    break;
                    
                case is_int($tokens->isTokenText(')')):
                case is_int($tokens->isTokenText(',')):
                    break 2;
                    
                default:
                    break 2;
                            
            }
        }
        
        return $columnDefinition;
    }
}
