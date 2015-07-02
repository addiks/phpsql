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

use Addiks\PHPSQL\Resource\Filesystem\Real;
use Addiks\PHPSQL\Value\Text\Filepath;

/**
 *
 */
abstract class Filesystem
{
    
    abstract public function getFileContents(Filepath $filePath);
    
    abstract public function putFileContents(Filepath $filePath, $content, $flags = 0);
    
    abstract public function fileOpen(Filepath $filePath, $mode);
    
    abstract public function fileClose($handle);
    
    abstract public function fileWrite($handle, $data);
    
    abstract public function fileRead($handle, $length);
    
    abstract public function fileTruncate($handle, $index);
    
    abstract public function fileSeek($handle, $offset);
    
    abstract public function fileTell($handle);
    
    abstract public function fileEOF($handle);
    
    abstract public function fileReadLine($handle);
}
