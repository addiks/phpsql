<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Value\Text\Directory;

use Addiks\PHPSQL\Value\Text\Directory;
use ErrorException;
use Exception;

/**
 * Value-Object representing path to folder where data-files can be stored.
 * If you need to put store files for your service on the hard drive,
 * this is the folder you would put it in.
 * This will often be a subfolder of the base-directory.
 * This should be outside of htdocs, if possible.
 *
 */
class Data extends Directory
{
    
    /**
     * Checks the gien directory for write-access.
     */
    protected function validate($directory)
    {
    
        parent::validate($directory);
    
        if (!is_writeable($directory)) {
            throw new ErrorException("The data-directory '{$directory}' needs to be writable!");
        }
    
    }
}
