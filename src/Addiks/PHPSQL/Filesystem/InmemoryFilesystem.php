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

    protected function getInternalFileHandle($filePath)
    {
        if ($this->fileIsDir($filePath)) {
            throw new ErrorException("Requested file is a folder ('{$filePath}')!");
        }
        $filePathParts = explode("/", $filePath);
        $fileName = array_pop($filePathParts);
        $folderPath = implode("/", $filePathParts);
        if (strlen($folderPath) <= 0) {
            $folderPath = "/";
        }
        if (!isset($this->fileResources[$folderPath])) {
            $this->fileResources[$folderPath] = array();
        } elseif (is_resource($this->fileResources[$folderPath])) {
            throw new ErrorException("Cannot create file '{$filePath}' in another file '{$folderPath}'!");
        }
        if (!isset($this->fileResources[$filePath])) {
            $this->fileResources[$filePath] = fopen("php://memory", "w+");
            $this->fileResources[$folderPath][$fileName] = $fileName;
        }
        return $this->fileResources[$filePath];
    }

    public function getFileContents($filePath)
    {
        $fileHandle = $this->getInternalFileHandle($filePath);
        fseek($fileHandle, 0, SEEK_END);
        $byteCount = ftell($fileHandle);
        fseek($fileHandle, 0);
        $content = fread($fileHandle, $byteCount);
        return $content;
    }
    
    public function putFileContents($filePath, $content, $flags = 0)
    {
        $fileHandle = $this->getInternalFileHandle($filePath);
        $byteCount = strlen($content);
        ftruncate($fileHandle, $byteCount);
        fseek($fileHandle, 0);
        fwrite($fileHandle, $content);
    }
    
    public function getFile($filePath, $mode = "a+")
    {
        $resourceProxy = null;

        if (!$this->fileIsDir($filePath)) {
            $fileHandle = $this->getInternalFileHandle($filePath);
            $resourceProxy = new FileResourceProxy($fileHandle, $mode);
        }

        return $resourceProxy;
    }

    public function fileOpen($filePath, $mode)
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
            $filePathParts = explode("/", $filePath);
            $fileName = array_pop($filePathParts);
            $folderPath = implode("/", $filePathParts);
            if (strlen($folderPath) <= 0) {
                $folderPath = "/";
            }
            fclose($this->fileResources[$filePath]);
            unset($this->fileResources[$filePath]);
            unset($this->fileResources[$folderPath][$fileName]);
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
        return array_values($files);
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
     *
     * @param string $dir
     */
    public static function rrmdir($dir)
    {
        
    }
}
