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

namespace Addiks\PHPSQL\Filesystem;

use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Value\Text\Filepath;

/**
 * Emulates a file-system completely in-memory.
 * All files will be lost after process-termination.
 */
class InmemoryFilesystem implements FilesystemInterface
{
    
    /**
     * Holds all file resources to inmemory files.
     *
     * These are NOT the resources that get returned, but only for internal usage.
     * Resources to give away (e.g.: fopen) are always proxies to these ones.
     *
     * array(
     *  "/foo" => array("bar", "baz"),
     *  "/foo/bar" => (resource)$resourceA),
     *  "/foo/baz" => (resource)$resourceB),
     * )
     *
     * @var array
     */
    protected $fileResources = array();

    protected function getInternalFileHandle(Filepath $filePath)
    {
        if ($this->fileIsDir($filePath)) {
            throw new ErrorException("Requested file is a folder ('{$filePath}')!");
        }
        if (!isset($this->fileResources[$filePath])) {
            $this->fileResources = fopen("php://memory", "w+");
        }
        return $this->fileResources[$filePath];
    }

    public function getFileContents(Filepath $filePath)
    {
        $fileHandle = $this->getInternalFileHandle($filePath);
        fseek($fileHandle, 0, SEEK_END);
        $byteCount = ftell($fileHandle);
        fseek($fileHandle, 0);
        $content = fread($fileHandle, $byteCount);
        return $content;
    }
    
    public function putFileContents(Filepath $filePath, $content, $flags = 0)
    {
        $fileHandle = $this->getInternalFileHandle($filePath);
        $byteCount = strlen($content);
        ftruncate($fileHandle, $byteCount);
        fseek($fileHandle, 0);
        fwrite($fileHandle, $content);
    }
    
    public function getFile(Filepath $filePath, $mode)
    {
        $resourceProxy = null;

        if (!$this->fileIsDir($filePath)) {
            $fileHandle = $this->getInternalFileHandle($filePath);
            $resourceProxy = new FileResourceProxy($fileHandle, $mode);
        }

        return $resourceProxy;
    }

    public function fileOpen(Filepath $filePath, $mode)
    {
        return $this->getFileProxy($filePath, $mode);
    }
    
    public function fileClose($handle)
    {
        $handle->close();
    }
    
    public function fileWrite($handle, $data)
    {
        $handle->write($data);
    }
    
    public function fileRead($handle, $length)
    {
        return $handle->read($length);
    }
    
    public function fileTruncate($handle, $size)
    {
        $handle->truncate($size);
    }
    
    public function fileSeek($handle, $offset, $seekMode = SEEK_SET)
    {
        $handle->seek($offset, $seekMode);
    }
    
    public function fileTell($handle)
    {
        return $handle->tell();
    }
    
    public function fileEOF($handle)
    {
        return $handle->eof();
    }
    
    public function fileReadLine($handle)
    {
        return $handle->readLine();
    }

    public function fileUnlink($filePath)
    {
        if (isset($this->fileResources[$filePath])) {
            if (is_array($this->fileResources[$filePath])) {
                throw new ErrorException("Cannot unlink a folder!");
            }
            fclose($this->fileResources[$filePath]);
            unset($this->fileResources[$filePath]);
        }
    }
    
    public function fileSize($filePath)
    {
        $size = 0;

        if (!$this->fileIsDir($filePath)) {
            $resource = $this->fileResources[$filePath];
            $stat = fstat($resource);
            $size = $stat['size'];
        }

        return $size;
    }

    public function fileExists($filePath)
    {
        return isset($this->fileResources[$filePath]);
    }

    public function fileIsDir($path)
    {
        return isset($this->fileResources[$path]) && is_array($this->fileResources[$path]);
    }

    public function getFilesInDir($path)
    {
        $files = array();
        if ($this->fileIsDir($path)) {
            $files = $this->fileResources[$path];
        }
        return $files;
    }

    /**
     * @return DirectoryIterator
     */
    public function getDirectoryIterator($path)
    {
        return new InmemoryDirectoryIterator($path, $this);
    }

    /**
     * removes recursive a whole directory
     * (copied from a comment in http://de.php.net/rmdir)

     * @author Someone else from the thing called internet (NOSPAMzentralplan dot de)
     * @param string $dir
     */
    public static function rrmdir($dir)
    {
        
    }

}
