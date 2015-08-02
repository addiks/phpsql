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
 * A Value-Object representing a filepath.
 * This does NOT check if the file actually exists on the file-system!
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 */
class Filepath extends Text
{
    
    public function getDirname()
    {
        return new self(substr($this->getValue(), 0, strrpos($this->getValue(), '/')));
    }
    
    public function getFileName()
    {
        return substr($this->getValue(), strrpos($this->getValue(), '/')+1);
    }
    
    public function isAbsolute()
    {
        $value = $this->getValue();
        return $value[0] === '/';
    }
}
