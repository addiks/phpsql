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

use Addiks\PHPSQL\Filesystem\FilesystemInterface;

class RealFilesystem implements FilesystemInterface
{

    public function getFileContents($filePath)
    {
        return file_get_contents($filePath);
    }

    public function putFileContents($filePath, $content, $flags = 0)
    {
        file_put_contents($filePath, $content, $flags);
    }

    public function getFile($filePath, $mode)
    {
        $resourceProxy = null;

        if (!$this->fileIsDir($filePath)) {
            $fileHandle = $this->fileOpen($filePath, $mode);
            $resourceProxy = new FileResourceProxy($fileHandle, $mode);
        }

        return $resourceProxy;
    }

    protected function fileOpen($filePath, $mode)
    {
        $handle = fopen($filePath, $mode);

        if (!is_resource($handle)) {
            return null;
        }

        return $handle;
    }

    public function fileUnlink($filePath)
    {
        unlink($filePath);
    }

    public function fileSize($filePath)
    {
        return filesize($filePath);
    }

    public function fileExists($filePath)
    {
        return file_exists($filePath);
    }

    public function getFilesInDir($path)
    {
        $files = array();
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    $files[] = $file;
                }
                closedir($dh);
            }
        }
        return $files;
    }

    /**
     * @return DirectoryIterator
     */
    public function getDirectoryIterator($path)
    {
        return new DirectoryIterator($path);
    }

}
