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

namespace Addiks\PHPSQL\Resource;

use Addiks\PHPSQL\Entity\Mount;
use ArrayIterator;

/**
 * Reads all mounts from /etc/fstab.
 * Gets used for filesystem-based cronjobs.
 * @see \Addiks\Tasks\Resource\FilesystemEvents
 */
class Mounts implements \IteratorAggregate
{
    
    const FSTAB_FILEPATH = "/etc/fstab";
    
    public function getMounts()
    {
        
        $readHandle = fopen(self::FSTAB_FILEPATH, "r");
        
        $mountEntity = new Mount();
        
        $mounts = array();
        while (!feof($readHandle)) {
            $line = fgets($readHandle);
            
            if (strlen(trim($line))<=0 || ltrim($line)[0] === '#') {
                continue;
            }
            
            $cells = preg_split("/\s+/is", $line);
            
            if (count($cells) < 6) {
                continue;
            }
            
            list($source, $path, $type, $optionString, $dump, $pass) = $cells;
            
            $mountEntity->setSource($source);
            $mountEntity->setMountPoint($path);
            $mountEntity->setType($type);
            $mountEntity->setOptions(explode(",", $optionString));
            $mountEntity->setDump($dump);
            $mountEntity->setPass($pass);
            
            $mounts[] = clone $mountEntity;
        }
        
        fclose($readHandle);
        
        return $mounts;
    }
    
    public function getIterator()
    {
        return new ArrayIterator($this->getMounts());
    }
}
