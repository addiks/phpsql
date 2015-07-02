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

namespace Addiks\PHPSQL\Value\Specifier;

use ErrorException;
use Addiks\PHPSQL\Value\Text\Line;

class DatabaseSpecifier extends Line
{
    
    public function getDatabase()
    {
        return $this->getValue();
    }
    
    protected function validate($value)
    {
        
        parent::validate($value);
        
        if (!preg_match("/^[a-zA-Z0-9:_-]+$/is", $value)) {
            throw new ErrorException("Invalid database-specifier given: '{$value}'!");
        }
    }
}
