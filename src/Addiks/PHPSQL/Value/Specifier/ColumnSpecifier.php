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

class ColumnSpecifier extends TableSpecifier
{
    
    public function getDatabase()
    {
        $parts = explode(".", $this->getValue());
        switch(count($parts)){
            case 3:
                return $parts[0];
        }
    }
    
    public function getTable()
    {
        $parts = explode(".", $this->getValue());
        switch(count($parts)){
            case 2:
                return $parts[0];
    
            case 3:
                return $parts[1];
        }
    }
    
    public function getColumn()
    {
        $parts = explode(".", $this->getValue());
        switch(count($parts)){
            case 1:
                return $parts[0];
                
            case 2:
                return $parts[1];
                
            case 3:
                return $parts[2];
        }
    }
    
    protected function validate($value)
    {
        
        $valueArray = explode(".", $value);
        if (count($valueArray)>1) {
            array_pop($valueArray);
        }
        
        parent::validate(implode(".", $valueArray));
    
        if (!preg_match("/^([a-zA-Z0-9:_-]+\.)?([a-zA-Z0-9:_-]+\.)?[a-zA-Z0-9:_-]+$/is", $value)) {
            throw new ErrorException("Invalid column-specifier given: '{$value}'!");
        }
    }
}
