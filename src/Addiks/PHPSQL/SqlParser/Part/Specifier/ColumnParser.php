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

namespace Addiks\PHPSQL\SqlParser\Part\Specifier;

use Addiks\PHPSQL\Value\Specifier\ColumnSpecifier as ColumnSpecifier;

use Addiks\PHPSQL\SqlParser\Part;

use ErrorException;

use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;

use Addiks\PHPSQL\Iterators\SQLTokenIterator;

class ColumnParser extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING, T_VARIABLE]));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
        
        $parts = array();
        
        do {
            if ($tokens->seekTokenNum(T_VARIABLE)) {
                $part = \Addiks\PHPSQL\Variable::factory($tokens->getCurrentTokenString());
                
            } elseif ($tokens->seekTokens([T_STRING, T_CONSTANT_ENCAPSED_STRING])) {
                $part = $tokens->getCurrentTokenString();
                    
                if ($part[0]==='`' && $part[strlen($part)-1]==='`') {
                    $part = substr($part, 1, strlen($part)-2);
                } elseif ($part[0]==='"' && $part[strlen($part)-1]==='"') {
                    $part = substr($part, 1, strlen($part)-2);
                } elseif ($part[0]==="'" && $part[strlen($part)-1]==="'") {
                    $part = substr($part, 1, strlen($part)-2);
                }
                    
            } else {
                throw new ErrorException("Tried to convert sql-column-specifier when token-iterator does not point to T_STRING!");
            }
            
            $parts[] = $part;
            
        } while ($tokens->seekTokenText("."));
        
        $specifier = ColumnSpecifier::factory(implode(".", $parts));
        
        return $specifier;
    }
}
