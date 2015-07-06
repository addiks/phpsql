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

use Addiks\PHPSQL\Value\Specifier\TableSpecifier as TableSpecifier;

use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\SQLTokenIterator;

use Addiks\PHPSQL\SqlParser\Part;

use Addiks\PHPSQL\TokenIterator;

class TableParser extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(T_STRING));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
    
        $parts = array();
    
        do {
            $tokens->seekIndex($tokens->getExclusiveTokenIndex());
            
            $part = $tokens->getCurrentTokenString();
                
            if ($part[0]==='`' && $part[strlen($part)-1]==='`') {
                $part = substr($part, 1, strlen($part)-2);
            }
                
            $parts[] = $part;
    
        } while ($tokens->seekTokenText(".") && !$tokens->isTokenText('*'));
        
        return TableSpecifier::factory(implode(".", $parts));
    }
}
