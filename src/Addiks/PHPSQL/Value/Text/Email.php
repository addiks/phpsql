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

use Zend\Mail\Address\AddressInterface;

use Zend\Validator\EmailAddress as EmailValidator;

use InvalidArgumentException;

/**
 * Value-Object representing an email-address.
 * Validates the mail-address using "Zend\Validate\EmailAddress".
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 *
 * @Addiks\Depency(repository="https://packages.zendframework.com/", package="zendframework/zend-validator")
 * @Addiks\Depency(repository="https://packages.zendframework.com/", package="zendframework/zend-mail")
 */
class Email extends Text implements AddressInterface
{
    
    protected static function filter($value)
    {
        return trim(parent::filter($value));
    }
    
    /**
     * Validates the mail-address using Zend.
     * @param string $value
     * @throws InvalidArgumentException
     */
    protected function validate($value)
    {
        
        parent::validate($value);
        
        $validator = new EmailValidator();
        
        if (!$validator->isValid($value)) {
            throw new ErrorException("Email address '{$value}' is invalid!");
        }
    }
    
    public function getEmail()
    {
        return (string)$this;
    }
    
    public function getName()
    {
        return null;
    }
    
    public function toString()
    {
        return $this->__toString();
    }
}
