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

namespace Addiks\PHPSQL\Value\Text;

use Addiks\PHPSQL\Value\Text;

/**
 * Value Object representing one line of text.
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 */
class Line extends Text
{
    
    /**
     * Creates a new value object.
     *
     * If the value is invalid, an invalid-argument-exception is thrown.
     * @see self::validate()
     *
     * @param scalar $value
     * @throws InvalidArgumentException
     */
    public static function factory($value)
    {
        return new static($value);
    }

    /**
     * Check for datatype string.
     * @param string $value
     * @throws  Exception\Error
     */
    protected function validate($value)
    {
        
        parent::validate($value);
        
        if (substr_count($value, "\n")>0) {
            throw new  Exception\InvalidValue("A one-liner text cannot contain a line-break!");
        }
        
    }
}
