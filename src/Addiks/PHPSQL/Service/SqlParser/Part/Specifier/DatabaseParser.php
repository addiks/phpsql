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

namespace Addiks\PHPSQL\Service\SqlParser\Part\Specifier;

use Addiks\PHPSQL\Value\Specifier\DatabaseSpecifier;

use Addiks\PHPSQL\Service\SqlParser\Part;

use Addiks\PHPSQL\Entity\Job\Part;
use Addiks\Analyser\Tool\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

use Addiks\PHPSQL\Tool\SQLTokenIterator;

class DatabaseParser extends Part
{
    
    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(T_STRING));
    }
    
    public function convertSqlToJob(SQLTokenIterator $tokens)
    {
    
        $parts = array();
    
        do {
            if (!$tokens->seekTokenNum(T_STRING)) {
                throw new Error("Tried to convert sql-database-specifier when token-iterator does not point to T_STRING!");
            }
            
            $part = $tokens->getCurrentTokenString();
                
            if ($part[0]==='`' && $part[strlen($part)-1]==='`') {
                $part = substr($part, 1, strlen($part)-2);
            }
                
            $parts[] = $part;
                
        } while ($tokens->seekTokenText("."));
    
        $specifier = DatabaseSpecifier::factory(implode(".", $parts));
        
        return $specifier;
    }
}
