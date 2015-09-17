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

namespace Addiks\PHPSQL\Iterators;

use ErrorException;
use Addiks\PHPSQL\Value;
use Addiks\PHPSQL\Value\Enum;

/**
 * This is a very powerful tool to work with token-arrays.
 *
 *
 * @see token_get_all()
 */
class TokenIterator implements \Iterator, \ArrayAccess
{
    
    public function __construct($input, $needsLineNumbers = false)
    {
        
        if (is_array($input)) {
            $this->tokenArray = $input;
            
        } elseif ($input instanceof File) {
            $this->tokenArray = token_get_all($input->getContents());
            
        } elseif (file_exists($input)) {
            $this->tokenArray = token_get_all(file_get_contents($input));
            
        } elseif (is_string($input)) {
            $this->tokenArray = token_get_all($input);
            
        } else {
            throw new ErrorException("Invalid input given to TokenIterator, needs to be filepath, php-string or token-array!");
        }
        
        if ($needsLineNumbers) {
            $this->fillLineNumbers();
        }
    }
    
    private $lineNumbersFilled = false;
    
    private function fillLineNumbers()
    {
        
        if (!$this->lineNumbersFilled) {
            $this->lineNumbersFilled = true;
            
            $lineNumber = 1;
            foreach ($this->tokenArray as $tokenIndex => &$token) {
                if (is_array($token)) {
                    if (is_int($token[0]) || $token[0] instanceof Value) {
                        $tokenText = $token[1];
                        $tokenNum  = $token[0];
                            
                    } else {
                        $tokenText = $token[0];
                        $tokenNum  = null;
                    }
                } else {
                    $tokenText = $token;
                    $tokenNum  = null;
                }
                    
                $token = array(
                    0 => $tokenNum,
                    1 => $tokenText,
                    2 => $lineNumber,
                );
                    
                $lineNumber += substr_count($tokenText, "\n");
            
            }
        }
    }
    
    private $faults = array();
    
    public function clearFaults()
    {
        $this->faults = array();
    }
    
    public function addFault(Entity\Exception $fault)
    {
        $this->faults[] = $fault;
    }
    
    public function setFaults(array $faults)
    {
        $this->clearFaults();
        foreach ($faults as $fault) {
            $this->addFault($fault);
        }
    }
    
    public function getFaults()
    {
        return $this->faults;
    }
    
    private $index = 0;
    
    public function getIndex()
    {
        return $this->index;
    }
    
    public function seekIndex($index)
    {
        $this->index = (int)$index;
    }
    
    private $tokenArray;
    
    public function getTokenArray()
    {
        return $this->tokenArray;
    }
    
    public function getIterator()
    {
        return new \ArrayIterator($this->tokenArray);
    }
    
    /**
     * search forward,
     * start at next index.
     */
    const NEXT = "next";
    
    /**
     * search forward,
     * start at current index.
     */
    const CURRENT = "current";
    
    /**
     * search backward,
     * start at previous index.
     */
    const PREVIOUS = "previous";
    
    ##### SEEK
    
    public function seekTokens(array $searchTokens, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        $seekIndex = $this->isTokens($searchTokens, $direction, $allowedTokensBetween, $useDefaultIgnores);
        if (!is_int($seekIndex)) {
            return false;
        }
        $this->seekIndex($seekIndex);
        return true;
    }
    
    public function seekToken($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        $seekIndex = $this->isToken($searchToken, $direction, $allowedTokensBetween, $useDefaultIgnores);
        if (!is_int($seekIndex)) {
            return false;
        }
        $this->seekIndex($seekIndex);
        return true;
    }
    
    public function seekTokenNum($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        $seekIndex = $this->isTokenNum($searchToken, $direction, $allowedTokensBetween, $useDefaultIgnores);
        if (!is_int($seekIndex)) {
            return false;
        }
        $this->seekIndex($seekIndex);
        return true;
    }
    
    public function seekTokenText($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        $seekIndex = $this->isTokenText($searchToken, $direction, $allowedTokensBetween, $useDefaultIgnores);
        if (!is_int($seekIndex)) {
            return false;
        }
        $this->seekIndex($seekIndex);
        return true;
    }
    
    ##### IS
    
    public function isTokens(array $searchTokens, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        foreach ($searchTokens as $searchToken) {
            $methodName = is_int($searchToken) ? 'isTokenNum' : 'isToken';
            
            $index = $this->$methodName($searchToken, $direction, $allowedTokensBetween, $useDefaultIgnores);
            
            if (is_int($index)) {
                return $index;
            }
        }
        return false;
    }
    
    public function isToken($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        if (is_array($allowedTokensBetween)) {
            foreach ($allowedTokensBetween as $betweenToken) {
                if (!is_string($betweenToken) && !is_int($betweenToken)) {
                    $betweenToken = ($betweenToken instanceof Value) ?$betweenToken->getValue() :$betweenToken;
                }
                $allowedTokensBetween[$betweenToken] = $betweenToken;
            }
        }
        
        if (!is_array($allowedTokensBetween)) {
            $allowedTokensBetween = ($allowedTokensBetween instanceof Value) ?$allowedTokensBetween->getValue() :$allowedTokensBetween;
            $allowedTokensBetween = array($allowedTokensBetween => $allowedTokensBetween);
        }
        
        // tokens that are ignored per default
        if ($useDefaultIgnores===true) {
            $allowedTokensBetween[T_WHITESPACE] = T_WHITESPACE;
            $allowedTokensBetween[T_COMMENT] = T_COMMENT;
            $allowedTokensBetween[T_DOC_COMMENT] = T_DOC_COMMENT;
        }
        
        switch($direction){
            case self::NEXT:
                $offset = 1;
                $increment = true;
                break;
        
            case self::CURRENT:
                $offset = 0;
                $increment = true;
                break;
                    
            case self::PREVIOUS:
                $offset = -1;
                $increment = false;
                break;
                
            default:
                throw new ErrorException("Unknown TokenIterator direction: '{$direction}'!");
        }
        
        $tokens = $this->getTokenArray();
        for ($i = $this->getIndex()+$offset; $i<count($tokens); $increment ? $i++ : $i--) {
            if (isset($tokens[$i][0])) {
                $tokenDescriber = ($tokens[$i][0] instanceof Value) ?$tokens[$i][0]->getValue() :$tokens[$i][0];
            } else {
                $tokenDescriber = null;
            }
            
            if ($tokens[$i] === $searchToken) {
                return $i;
            } elseif (!in_array($tokens[$i], $allowedTokensBetween)
                && !(!is_null($tokenDescriber) && isset($allowedTokensBetween[$tokenDescriber]))
                &&  (!isset($tokens[$i][1]) || !@in_array($tokens[$i][1], $allowedTokensBetween))) {
                return false;
            }
        }
        
        return false;
    }
    
    public function isTokenNum($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        if (is_array($allowedTokensBetween)) {
            foreach ($allowedTokensBetween as $betweenToken) {
                if (!is_string($betweenToken) && !is_int($betweenToken)) {
                    $betweenToken = ($betweenToken instanceof Value) ?$betweenToken->getValue() :$betweenToken;
                }
                $allowedTokensBetween[$betweenToken] = $betweenToken;
            }
        }
        
        if (!is_array($allowedTokensBetween)) {
            $allowedTokensBetween = ($allowedTokensBetween instanceof Value) ?$allowedTokensBetween->getValue() :$allowedTokensBetween;
            $allowedTokensBetween = array($allowedTokensBetween => $allowedTokensBetween);
        }
        
        // tokens that are ignored per default
        if ($useDefaultIgnores===true) {
            $allowedTokensBetween[T_WHITESPACE] = T_WHITESPACE;
            $allowedTokensBetween[T_COMMENT] = T_COMMENT;
            $allowedTokensBetween[T_DOC_COMMENT] = T_DOC_COMMENT;
        }
        
        switch($direction){
            case self::NEXT:
                $offset = 1;
                $increment = true;
                break;
        
            case self::CURRENT:
                $offset = 0;
                $increment = true;
                break;
                    
            case self::PREVIOUS:
                $offset = -1;
                $increment = false;
                break;
                
            default:
                throw new ErrorException("Unknown TokenIterator direction: '{$direction}'!");
        }
        
        $tokens = $this->getTokenArray();
        for ($i = $this->getIndex()+$offset; $i<count($tokens); $increment ? $i++ : $i--) {
            if (isset($tokens[$i][0])) {
                $tokenDescriber = ($tokens[$i][0] instanceof Value) ?$tokens[$i][0]->getValue() :$tokens[$i][0];
            } else {
                $tokenDescriber = null;
            }
            
            if (is_array($tokens[$i]) && $tokens[$i][0] === $searchToken) {
                return $i;
                
            } elseif (!in_array($tokens[$i], $allowedTokensBetween)
                     && !(!is_null($tokenDescriber) && isset($allowedTokensBetween[$tokenDescriber]))
                     && !(isset($tokens[$i][1]) && isset($allowedTokensBetween[(string)$tokens[$i][1]]))) {
                return false;
            }
        }
        
        return false;
    }
    
    public function isTokenText($searchToken, $direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        if (is_array($allowedTokensBetween)) {
            foreach ($allowedTokensBetween as $betweenToken) {
                if (!is_string($betweenToken) && !is_int($betweenToken)) {
                    $betweenToken = ($betweenToken instanceof Value) ?$betweenToken->getValue() :$betweenToken;
                }
                $allowedTokensBetween[$betweenToken] = $betweenToken;
            }
        }
        
        if (!is_array($allowedTokensBetween)) {
            $allowedTokensBetween = ($allowedTokensBetween instanceof Value) ?$allowedTokensBetween->getValue() :$allowedTokensBetween;
            $allowedTokensBetween = array($allowedTokensBetween => $allowedTokensBetween);
        }
        
        // tokens that are ignored per default
        if ($useDefaultIgnores===true) {
            $allowedTokensBetween[T_WHITESPACE]  = T_WHITESPACE;
            $allowedTokensBetween[T_COMMENT]     = T_COMMENT;
            $allowedTokensBetween[T_DOC_COMMENT] = T_DOC_COMMENT;
        }
        
        switch($direction){
            case self::NEXT:
                $offset = 1;
                $increment = true;
                break;
        
            case self::CURRENT:
                $offset = 0;
                $increment = true;
                break;
                    
            case self::PREVIOUS:
                $offset = -1;
                $increment = false;
                break;
                
            default:
                throw new ErrorException("Unknown TokenIterator direction: '{$direction}'!");
        }
        
        $tokens = $this->getTokenArray();
        for ($i = $this->getIndex()+$offset; $i<count($tokens); $increment ? $i++ : $i--) {
            if (!is_array($tokens[$i])) {
                return false;
            }
            
            if (isset($tokens[$i][0])) {
                $tokenDescriber = ($tokens[$i][0] instanceof Value) ?$tokens[$i][0]->getValue() :$tokens[$i][0];
            } else {
                $tokenDescriber = null;
            }
                
            if ($tokens[$i][1] === $searchToken) {
                return $i;
            } elseif (!in_array($tokens[$i], $allowedTokensBetween)
                     && !(!is_null($tokenDescriber) && isset($allowedTokensBetween[$tokenDescriber]))
                     && !(isset($tokens[$i][1]) && isset($allowedTokensBetween[(string)$tokens[$i][1]]))) {
                return false;
            }
        }
        
        return false;
    }
    
    ##### INTELLIGENT GETTER
    
    public function getExclusiveTokenString($direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        $token = $this->getExclusiveToken($direction, $allowedTokensBetween, $useDefaultIgnores);
        
        if (is_array($token)) {
            if (is_int($token[0]) || $token[0] instanceof Enum) {
                return $token[1];
            } else {
                return (string)$token[0];
            }
        } else {
            return (string)$token;
        }
    }
    
    public function getExclusiveTokenNumber($direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        $token = $this->getExclusiveToken($direction, $allowedTokensBetween, $useDefaultIgnores);
        
        if (!is_array($token) || (!is_int($token[0]) && !$token[0] instanceof Enum)) {
            return null;
        }
        
        return $token[0];
    }
    
    public function getExclusiveToken($direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        $index = $this->getExclusiveTokenIndex($direction, $allowedTokensBetween, $useDefaultIgnores);
        $tokens = $this->getTokenArray();
        if (!isset($tokens[$index])) {
            return null;
        }
        return $tokens[$index];
    }
    
    public function getExclusiveTokenIndex($direction = self::NEXT, $allowedTokensBetween = array(), $useDefaultIgnores = true)
    {
        
        if (is_array($allowedTokensBetween)) {
            foreach ($allowedTokensBetween as $betweenToken) {
                if (!is_string($betweenToken) && !is_int($betweenToken)) {
                    $betweenToken = ($betweenToken instanceof Value) ?$betweenToken->getValue() :$betweenToken;
                }
                $allowedTokensBetween[$betweenToken] = $betweenToken;
            }
        }
        
        if (!is_array($allowedTokensBetween)) {
            $allowedTokensBetween = ($allowedTokensBetween instanceof Value) ?$allowedTokensBetween->getValue() :$allowedTokensBetween;
            $allowedTokensBetween = array($allowedTokensBetween => $allowedTokensBetween);
        }
        
        // tokens that are ignored per default
        if ($useDefaultIgnores===true) {
            $allowedTokensBetween[T_WHITESPACE]  = T_WHITESPACE;
            $allowedTokensBetween[T_COMMENT]     = T_COMMENT;
            $allowedTokensBetween[T_DOC_COMMENT] = T_DOC_COMMENT;
        }
        
        switch($direction){
            case self::NEXT:
                $offset = 1;
                $increment = true;
                break;
                
            case self::CURRENT:
                $offset = 0;
                $increment = true;
                break;
            
            case self::PREVIOUS:
                $offset = -1;
                $increment = false;
                break;
                
            default:
                throw new ErrorException("Unknown TokenIterator direction: '{$direction}'!");
        }
        
        $tokens = $this->getTokenArray();
        for ($i = $this->getIndex()+$offset; $i<count($tokens); $increment ? $i++ : $i--) {
            if (isset($tokens[$i][0])) {
                $tokenDescriber = ($tokens[$i][0] instanceof Value) ?$tokens[$i][0]->getValue() :$tokens[$i][0];
            } else {
                $tokenDescriber = null;
            }
            
            if (!in_array($tokens[$i], $allowedTokensBetween)
                    && !(!is_null($tokenDescriber) && isset($allowedTokensBetween[$tokenDescriber]))
                    && !(isset($tokens[$i][1])     && isset($allowedTokensBetween[(string)$tokens[$i][1]]))) {
                return $i;
            }
        }
        return $this->getIndex();
    }
    
    ##### HELPER
    
    public function getCurrentToken()
    {
        return $this->tokenArray[$this->getIndex()];
    }
    
    public function getNextToken()
    {
        $this->next();
        $token = $this->current();
        $this->prev();
        return $token;
    }
    
    public function getPreviousToken()
    {
        $this->prev();
        $token = $this->current();
        $this->next();
        return $token;
    }
    
    public function getCurrentTokenString()
    {
        $token = $this->getCurrentToken();
        
        if (is_array($token)) {
            if (is_int($token[0]) || $token[0] instanceof Enum || is_null($token[0])) {
                return $token[1];
            } else {
                return (string)$token[0];
            }
        } else {
            return (string)$token;
        }
    }
    
    public function getNextTokenString()
    {
        $this->next();
        $string = $this->getCurrentTokenString();
        $this->prev();
        return $string;
    }
    
    public function getPreviousTokenString()
    {
        $this->prev();
        $string = $this->getCurrentTokenString();
        $this->next();
        return $string;
    }
    
    public function getCurrentTokenNumber()
    {
        $token = $this->getCurrentToken();
        if (!is_array($token)) {
            return null;
        }
        return $token[0];
    }
    
    public function getNextTokenNumber()
    {
        $this->next();
        $num = $this->getCurrentTokenNumber();
        $this->prev();
        return $num;
    }
    
    public function getPreviousTokenNumber()
    {
        $this->prev();
        $num = $this->getCurrentTokenNumber();
        $this->next();
        return $num;
    }
    
    private $lineCache = array(
        0 => 1 // line 1 is always in token-index 0
    );
    
    public function getLineNumber($tokenNum = null)
    {
        
        if (is_null($tokenNum)) {
            $tokenNum = $this->getIndex();
        } else {
            $tokenNum = (int)$tokenNum;
            if ($tokenNum < 0) {
                $tokenNum = 0;
            }
        }
        
        if (isset($this->lineCache[$tokenNum])) {
            return $this->lineCache[$tokenNum];
        }
        
        $cacheTokenNum=$tokenNum-1;
        while (!isset($this->lineCache[$cacheTokenNum])) {
            $cacheTokenNum--;
        }
        $line = $this->lineCache[$cacheTokenNum];
        
        for ($index=$cacheTokenNum; $index<$tokenNum; $index++) {
            $tokenText = is_array($this->getTokenArray()[$index])
                ? $this->getTokenArray()[$index][1]
                : $this->getTokenArray()[$index];
            
            $line += substr_count($tokenText, "\n");
        }
        
        $this->lineCache[$tokenNum] = $line;
        
        return $line;
    }
    
    public function getColumnNumber($tokenNum = null)
    {
        
        if (is_null($tokenNum)) {
            $tokenNum = $this->getIndex();
        } else {
            $tokenNum = (int)$tokenNum;
            if ($tokenNum < 0) {
                $tokenNum = 0;
            }
        }
        
        $column = 1;
        for ($index=$tokenNum-1; $index>=0; $index--) {
            if (is_array($this->getTokenArray()[$index])) {
                if (is_int($this->getTokenArray()[$index][0]) || $this->getTokenArray()[$index][0] instanceof Enum) {
                    $tokenText = $this->getTokenArray()[$index][1];
                } else {
                    $tokenText = $this->getTokenArray()[$index][0];
                }
            } else {
                $tokenText = $this->getTokenArray()[$index];
            }
            
            $tokenText = str_replace("_t", "    ", $tokenText);
            
            if (strpos($tokenText, "\n")!==false) {
                $column += strlen($tokenText) - strrpos($tokenText, "\n") -1;
                break;
            } else {
                $column += strlen($tokenText);
            }
            
        }
        
        return $column;
    }
    
    public function rebuildPHPString($beginLine = null, $endLine = null)
    {
        $tokens = $this->getTokenArray();
        if (is_null($beginLine) || $beginLine < 0) {
            $beginLine = 0;
        }
        $this->fillLineNumbers();
        if (is_null($endLine)) {
            $lastToken = end($tokens);
            if (isset($lastToken[2])) {
                $endLine = $lastToken[2];
            } else {
                $endLine = $this->getLineNumber(count($this->tokenArray)-1);
            }
        }
        
        $phpString = "";
        for ($i=0; isset($tokens[$i]) && $tokens[$i][2]<=$endLine; $i++) {
            if ($tokens[$i][2]<$beginLine) {
                continue;
            }
            $phpString .= $tokens[$i][1];
        }
        return $phpString;
    }
    
    public function countTokenOccourences($needle, $indexStart = 0, $indexEnd = null)
    {
        if (is_null($indexEnd) || $indexEnd > count($this->tokenArray)) {
            $indexEnd = count($this->tokenArray);
        }

        $occourenceCount = 0;
        for ($index = $indexStart; $index <= $indexEnd; $index++) {
            $token = $this->tokenArray[$index];
            if ((is_array($token) && ($token[0] === $needle || $token[1] === $needle)) || $token === $needle) {
                $occourenceCount++;
            }
        }

        return $occourenceCount;
    }

    ### ARRAY ACCESS
    
    public function offsetExists($offset)
    {
        return isset($this->tokenArray[$offset]);
    }
    
    public function offsetGet($offset)
    {
        return $this->tokenArray[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        $this->tokenArray[$offset] = $value;
    }
    
    public function offsetUnset($offset)
    {
        unset($this->tokenArray[$offset]);
    }
    
    ### ITERATOR
    
    public function rewind()
    {
        $this->index = 0;
    }
    
    public function valid()
    {
        return isset($this->tokenArray[$this->index]);
    }
    
    public function key()
    {
        return $this->index;
    }
    
    public function current()
    {
        return $this->tokenArray[$this->index];
    }
    
    public function next()
    {
        if (!$this->valid()) {
            throw new ErrorException("Tried to increment token-iterator-pointer when pointer is invalid (EOF)!");
        }
        $this->index++;
    }
    
    public function prev()
    {
        if (!$this->valid()) {
            throw new ErrorException("Tried to increment token-iterator-pointer when pointer is invalid (EOF)!");
        }
        $this->index--;
    }
    
    public function isAtEnd()
    {
        return $this->getIndex() >= count($this->getTokenArray())-1;
    }
}
