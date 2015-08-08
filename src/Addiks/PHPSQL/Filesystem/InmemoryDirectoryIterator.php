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

use DirectoryIterator;
use Addiks\PHPSQL\Filesystem\InmemoryFilesystem;

class InmemoryDirectoryIterator extends DirectoryIterator
{
    public function __construct($path, InmemoryFilesystem $filesystem = null)
    {
        $this->path = $path;
        $this->filesystem = $filesystem;
    }

    protected $filesystem;

    protected $path;

    protected $indexInPath;

    public function current()
    {
        return $this;
    }

    public function getATime()
    {
        return 0;
    }

    public function getBasename($suffix)
    {
        return basename($this->getPath(), $suffix);
    }

    public function getCTime()
    {
        return 0;
    }

    public function getExtension()
    {
        $extension = "";
        $parts = explode(".", $this->getFilename());
        if (count($parts)>1) {
            $extension = end($parts);
        }
        return $extension;
    }

    public function getFilename()
    {
        $filename = "";
        $files = $this->filesystem->getFilesInDir($this->getPath());
        if (count($files) > $this->indexInPath) {
            $filename = $files[$this->indexInPath];
        }
        return $filename;
    }

    public function getGroup()
    {
        return 0;
    }

    public function getInode()
    {
        return 0;
    }

    public function getMTime()
    {
        return 0;
    }

    public function getOwner()
    {
        return 0;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getPathname()
    {
        $pathname = $this->getPath();
        if (!is_null($this->indexInPath)) {
            $pathname .= "/{$this->getFilename()}";
        }
        return $pathname;
    }

    public function getPerms()
    {
        return 0;
    }

    public function getSize()
    {
        return $this->filesystem->fileSize($this->getPathname());
    }

    public function getType()
    {
        return $this->isDir() ?'dir' :'file';
    }

    public function isDir()
    {
        return $this->filesystem->fileIsDir($this->getPathname());
    }

    public function isDot()
    {
        return in_array($this->getFilename(), ['.', '..']);
    }

    public function isExecutable()
    {
        return false;
    }

    public function isFile()
    {
        return !$this->filesystem->fileIsDir($this->getPathname());
    }

    public function isLink()
    {
        return false;
    }

    public function isReadable()
    {
        return true;
    }

    public function isWritable()
    {
        return true;
    }

    public function key()
    {
        return $this->getFilename();
    }

    public function next()
    {
        $files = $this->filesystem->getFilesInDir($this->getPath());
        $this->indexInPath += 1;

        if (count($files) <= $this->indexInPath) {
            $this->indexInPath = null;
        }
    }

    public function rewind()
    {
        $files = $this->filesystem->getFilesInDir($this->getPath());
        if (count($files) > 0) {
            $this->indexInPath = 0;
        } else {
            $this->indexInPath = null;
        }
    }

    public function seek($position)
    {
        $files = $this->filesystem->getFilesInDir($this->getPath());
        $this->indexInPath = $position;

        if (count($files) <= $this->indexInPath) {
            $this->indexInPath = null;
        }
    }

    public function __toString()
    {
        return $this->getPathname();
    }

    public function valid()
    {
        return !is_null($this->indexInPath);
    }
}
