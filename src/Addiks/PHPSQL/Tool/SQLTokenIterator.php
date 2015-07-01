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

namespace Addiks\PHPSQL\Tool;

use Addiks\Analyser\Tool\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

class SQLTokenIterator extends TokenIterator
{
    
    public function __construct($input)
    {
    
        if (is_array($input)) {
            $tokenArray = $input;
        } elseif (is_string($input)) {
            $tokenArray = $this->tokenizeSqlStatement($input);
        } else {
            throw new Error("Invalid input given to SqlIterator, needs to be sql-string or token-array!");
        }
    
        parent::__construct($tokenArray);
    }
    
    /**
     * This turnes an SQL-statement-string into an sql-token-array.
     * @param string $statementString
     * @return array
     */
    public function tokenizeSqlStatement($statementString)
    {
        
#		$statementString = preg_replace("/\s+/is", " ", $statementString);
        $statementString = str_replace("\t", "  ", $statementString);
        
        $reflectionTokenEnum = new \ReflectionClass("\Addiks\PHPSQL\Value\Enum\Sql\SqlToken");
        
        $tokenNames = array_map(function ($tokenName) {
            return substr($tokenName, 2);
        }, array_keys($reflectionTokenEnum->getConstants()));
        
        $tokenNamePattern = "^(".implode("|", $tokenNames).")([^A-Z0-9_]|\$)";
        
        $userSpaceKeyWordPattern = "^([a-zA-Z0-9_-]+)([^A-Z_]|\$)";
        
        $stringQuotePattern  = "^\'[^\']*\'";
        $stringDQuotePattern = "^\"[^\"]*\"";
        $stringWordPattern   = "^\`[^\`]*\`";
        
        $operatorPattern = "^(".implode("|", ['<=>', '!=', '<>', '<=', '>=']).")([^A-Z_]|\$)";
        $otherCharactersPattern = "^[\\".implode("\\", str_split("+-*/,.()-=<>|&;!?", 1))."]";
        $commentPattern = "^\-\-[^\n]*\n";
        $multiLineComment = "^\/\*.*?\*\/";
        
        $numberPattern     = "^((0|\-?[1-9]([0-9]+)?)(\.[0-9]+)?)([^A-Z_]|\$)";
        $whiteSpacePattern = "^\s+";
        
        $variablePattern = "^(\:[a-zA-Z0-9_-]+|\?)([^A-Z_]|\$)";
        
        $position = 0;
        $statementStringLeft = substr($statementString, $position);
        
        $tokenArray = array();
        $line = 1;
        
        while (strlen($statementStringLeft)>0) {
            switch(true){
                
                case preg_match("/{$whiteSpacePattern}/", $statementStringLeft, $matches):
                    $position += strlen($matches[0]);
                    $tokenArray[] = [T_WHITESPACE, $matches[0], $line];
                    $line += substr_count($matches[0], "\n");
                    break;
                
            #	case $statementStringLeft[0]===' ':
            #		$position++;
            #		break;
                
                case preg_match("/{$numberPattern}/", $statementStringLeft, $matches):
                    $position += strlen($matches[1]);
                    $tokenArray[] = [T_NUM_STRING, $matches[1], $line];
                    break;
                    
                case preg_match("/{$stringQuotePattern}/", $statementStringLeft, $matches):
                case preg_match("/{$stringDQuotePattern}/", $statementStringLeft, $matches):
                    $position += strlen($matches[0]);
                    $tokenArray[] = [T_CONSTANT_ENCAPSED_STRING, $matches[0], $line];
                    $line += substr_count($matches[0], "\n");
                    break;
                    
                case preg_match("/{$commentPattern}/is", $statementStringLeft, $matches):
                    $position += strlen($matches[0]);
                    $tokenArray[] = [T_COMMENT, $matches[0], $line];
                    $line += substr_count($matches[0], "\n");
                    break;
                    
                case preg_match("/{$multiLineComment}/is", $statementStringLeft, $matches):
                    $position += strlen($matches[0]);
                    $tokenArray[] = [T_COMMENT, $matches[0], $line];
                    $line += substr_count($matches[0], "\n");
                    break;
                    
                
                case preg_match("/{$tokenNamePattern}/is", $statementStringLeft, $matches):
                    $tokenName = strtoupper("T_{$matches[1]}");
                    $position += strlen($matches[1]);
                    $tokenValue = call_user_func("\Addiks\PHPSQL\Value\Enum\Sql\SqlToken::{$tokenName}");
                    $tokenArray[] = [$tokenValue, $matches[1], $line];
                    break;
                    
                case preg_match("/{$variablePattern}/si", $statementStringLeft, $matches):
                    $position += strlen($matches[1]);
                    $tokenArray[] = [T_VARIABLE, $matches[1], $line];
                    break;
                    
                case preg_match("/{$operatorPattern}/is", $statementStringLeft, $matches):
                    $position += strlen($matches[1]);
                    $tokenArray[] = [$matches[1], $matches[1], $line];
                    break;
                    
                case preg_match("/{$otherCharactersPattern}/is", $statementStringLeft, $matches):
                    $position += strlen($matches[0]);
                    $tokenArray[] = [$matches[0], $matches[0], $line];
                    break;
                    
                case preg_match("/{$stringWordPattern}/", $statementStringLeft, $matches):
                #	$word = substr($matches[0], 1, strlen($matches[0])-2);
                    $position += strlen($matches[0]);
                    $tokenArray[] = [T_STRING, $matches[0], $line];
                    $line += substr_count($matches[0], "\n");
                    break;
                        
                case preg_match("/{$userSpaceKeyWordPattern}/si", $statementStringLeft, $matches):
                    $position += strlen($matches[1]);
                    $tokenArray[] = [T_STRING, $matches[1], $line];
                    break;
                    
                default:
                    $malformedSQLPart = substr($statementString, $position, 100);
                    throw new Error("Malformed SQL: '{$malformedSQLPart}'!");
            }
            $statementStringLeft = substr($statementString, $position);
        }
        
        return $tokenArray;
    }
    
    public function rebuildSqlString($beginLine = null, $endLine = null)
    {
        $tokens = $this->getTokenArray();
        if (is_null($beginLine) || $beginLine < 0) {
            $beginLine = 0;
        }
        if (is_null($endLine)) {
            $lastToken = end($tokens);
            $endLine = $lastToken[2];
        }
        
        $sqlString = "";
        for ($i=0; isset($tokens[$i]) && $tokens[$i][2]<=$endLine; $i++) {
            if ($tokens[$i][2]<$beginLine) {
                continue;
            }
            $sqlString .= $tokens[$i][1];
        }
        return $sqlString;
    }
}
