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

namespace Addiks\PHPSQL\Entity\Job;

use Addiks\PHPSQL\Entity\Job;

use Addiks\PHPSQL\Service\SqlParser;

/**
 * Represents a plain, complete SQL statement that can be executed.
 *
 * @see Executor
 * @see SqlParser
 */
abstract class Statement extends Job
{
    
    /**
     * Gets the result-specifier specifying how the result should look like.
     *
     * @return Specifier
     */
    public function getResultSpecifier()
    {
        return new Specifier();
    }
    
    /**
     * Checks if the statement is valid.
     * If not, an InvalidArgument is thrown.
     *
     * @throws InvalidArgument
     */
    public function validate()
    {
    }
}
