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

use ErrorException;
use Addiks\PHPSQL\Value\Text;

/**
 * Class representing an IP-address.
 * @see http://en.wikipedia.org/wiki/address
 */
class IpAddress extends Text
{

    public function validate($value)
    {
        
        parent::validate();
        
        if (!preg_match("/\d{1,3}(\.\d{1,3}){3}/is", $value)) {
            throw new ErrorException("Ip-address has to be like '123.45.67.189' (four numbers 0-255 seperated by dots)!");
        }
    }
}
