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

namespace Addiks\PHPSQL\Value\Database\Dsn;

use ErrorException;
use Addiks\PHPSQL\Value\Database\Dsn;
use Addiks\PHPSQL\Database;

class InmemoryDsn extends Dsn
{
    
    const PATTERN = "^[a-z0-9_]+$";
    
    const DRIVERNAME = "inmemory";
    
    private $databaseId = "default";
    
    protected static function filter($value)
    {
        $value = parent::filter($value);
        
        if (strpos($value, ":")===false) {
            $value = "inmemory:{$value}";
        }

        if (strlen($value)<=0) {
            return "inmemory:default";
            
        } else {
            return $value;
        }
    }
    
    public function validate($value)
    {
        
        parent::validate($value);
        
        $pattern = self::PATTERN;
        
        $parts = explode(":", $value);
        
        if (count($parts)<2
        || $parts[0] !== self::DRIVERNAME
        || !preg_match("/{$pattern}/is", $parts[1])) {
            throw new ErrorException("Invalid DSN for inmemory database: '{$value}'");
        }
    }
    
    public function getDatabaseId()
    {
        
        $parts = explode(":", $this->getValue());
        
        return (int)$parts[1];
    }
}
