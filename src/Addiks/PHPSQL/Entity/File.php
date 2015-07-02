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

namespace Addiks\PHPSQL\Entity;

use Addiks\PHPSQL\Entity;
use Addiks\PHPSQL\Value\Text\Filepath;

/**
 *
 * @author gerrit
 */
class File extends Entity
{
    
    public function __construct(Filepath $filePath)
    {
        
        $this->filePath = $filePath;
    }
    
    /**
     *
     * @var Filesystem
     */
    private $backend;
    
    /**
     *
     * @return Filesystem
     *
     */
    public function getBackend()
    {
        return $this->backend;
    }
    
    public function setBackend(Filesystem $filesystem)
    {
        $this->backend = $filesystem;
    }
    
    protected $filePath;
    
    public function getFilePath()
    {
        return $this->filePath;
    }
    
    protected $handle;
    
    ### BACKEND OPERATIONS
    
    public function getContents()
    {
        return $this->getBackend()->getFileContents($this->getFilePath());
    }
    
    /**
     *
     * @see file_set_contents
     * @param unknown_type $data
     * @param unknown_type $mode
     */
    public function setContent($data, $flags = 0)
    {
        $this->getBackend()->setFileContent($this->getFilePath(), $data, $flags);
    }
    
    public function open($mode)
    {
        
        $this->handle = $this->getBackend()->fileOpen($this->getFilePath(), $mode);
        
        return !is_null($this->handle);
    }
    
    public function close()
    {
        $this->getBackend()->fileClose($this->handle);
    }
    
    public function write($data)
    {
        
        if (is_null($this->handle)) {
            $this->open("a+");
        }
        
        $this->getBackend()->fileWrite($this->handle, $data);
    }
    
    public function read($length)
    {
        
        if (is_null($this->handle)) {
            $this->open("r");
        }
        
        $this->getBackend()->fileRead($this->handle, $length);
    }
    
    public function truncate($index)
    {
        
        if (is_null($this->handle)) {
            $this->open("a+");
        }
        
        $this->getBackend()->fileTruncate($this->handle, $index);
    }
    
    public function seek($index)
    {
        
        if (is_null($this->handle)) {
            $this->open("r");
        }
        
        $this->getBackend()->fileSeek($this->handle);
    }
    
    public function tell()
    {
        
        if (is_null($this->handle)) {
            $this->open("r");
        }
        
        return $this->getBackend()->fileTell($this->handle);
    }
    
    public function isEndOfFile()
    {
        
        if (is_null($this->handle)) {
            $this->open("r");
        }
        
        return $this->getBackend()->fileEOF($this->handle);
    }
    
    public function readLine()
    {
        
        if (is_null($this->handle)) {
            $this->open("r");
        }
        
        return $this->getBackend()->fileReadLine($this->handle);
    }
}
