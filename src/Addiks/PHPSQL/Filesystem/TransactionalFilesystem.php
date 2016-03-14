<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Filesystem;

use ErrorException;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Iterators\TransactionalInterface;
use Addiks\PHPSQL\Filesystem\TransactionalFile;
use Addiks\PHPSQL\Filesystem\FileInterface;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

/**
 * Provides transctional access to another filesystem.
 *
 * A transaction can be started with "beginTransaction".
 * All changes after that will be applied to the actual filesystem only when "commit" is called.
 * The changes can also be reversed with "rollback".
 *
 * @see https://en.wikipedia.org/wiki/Transaction_processing
 *
 * This is a flawed implementation and thus only temporary:
 *   This implementation creates meta-data which has to be in memory.
 *   The amount of this meta-data increases with the size of the transaction(s).
 *   That means that the size of the transactions is limited in this implementation.
 *   A better solution would put everything about all transactions into one big file
 *   and swap that onto the hard-disk if it get's too big.
 *   But for that i would need to invent a new binary data-structure first that fulfills all expectations.
 */
class TransactionalFilesystem implements FilesystemInterface, TransactionalInterface
{

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected $filesystem;

    protected $filesStack = array([]);

    protected $createdFilepathsStack = array([]);

    protected $deletedFilepathsStack = array([]);

    protected $directoriesStack = array([]);

    ### FILESYSTEM

    public function getFileContents($filePath)
    {
        /* @var $file TransactionalFile */
        $file = $this->getFile($filePath, "r");

        return $file->getData();
    }

    public function putFileContents($filePath, $content, $flags = 0)
    {
        /* @var $file TransactionalFile */
        $file = $this->getFile($filePath, "w");

        $file->setData($content);
    }

    public function getFile($filePath, $mode = "a+")
    {
        /* @var $result TransactionalFile */
        $result = null;

        foreach (array_reverse($this->filesStack, true) as $transactionNr => $files) {
            if (isset($files[$filePath])) {
                $result = $files[$filePath];
                for ($index = $transactionNr+1; $index < count($this->filesStack); $index++) {
                    $result->beginTransaction();
                    $this->filesStack[$index][$filePath] = $result;
                }
                break;
            }
        }

        if (is_null($result)) {
            /* @var $filesystem FilesystemInterface */
            $filesystem = $this->filesystem;

            if ($filesystem->fileExists($filePath) || count($this->createdFilepathsStack) < 0) {
                $realFile = $filesystem->getFile($filePath, $mode);

            } else {
                $realFile = new FileResourceProxy(fopen("php://memory", "w"));

                end($this->createdFilepathsStack);
                $this->createdFilepathsStack[key($this->createdFilepathsStack)][$filePath] = $filePath;
            }

            $result = new TransactionalFile($realFile);

            end($this->filesStack);
            $this->filesStack[key($this->filesStack)][$filePath] = $result;

            $filePathParts = explode("/", $filePath);
            do {
                array_pop($filePathParts);
                $folderPath = implode("/", $filePathParts);
                $this->directoriesStack[key($this->filesStack)][$folderPath] = $folderPath;
            } while (count($filePathParts) > 0);

            for ($index=1; $index < count($this->createdFilepathsStack); $index++) {
                $result->beginTransaction();
            }
        }

        return $result;
    }

    public function fileUnlink($filePath)
    {
        $result = null;

        if ($this->fileExists($filePath)) {
            if (count($this->deletedFilepathsStack) >= 1) {
                end($this->deletedFilepathsStack);
                $this->deletedFilepathsStack[key($this->deletedFilepathsStack)][$filePath] = $filePath;

            } else {
                /* @var $filesystem FilesystemInterface */
                $filesystem = $this->filesystem;

                $filesystem->fileUnlink($filePath);
            }
        }

        return $result;
    }

    public function fileSize($filePath)
    {
        $result = null;

        if ($this->fileExists($filePath)) {
            foreach (array_reverse($this->filesStack, true) as $transactionNr => $files) {
                if (isset($files[$filePath])) {
                    $result = $files[$filePath]->getSize();
                    break;
                }
            }

            if (is_null($result)) {
                /* @var $filesystem FilesystemInterface */
                $filesystem = $this->filesystem;

                $result = $filesystem->fileSize($filePath);
            }
        }

        return $result;
    }

    public function fileExists($filePath)
    {
        $result = null;

        foreach (array_reverse($this->filesStack, true) as $transactionNr => $files) {
            if (isset($files[$filePath])) {
                $result = true;
            }
            if (isset($this->createdFilepathsStack[$transactionNr][$filePath])) {
                $result = true;
            }
            if (isset($this->deletedFilepathsStack[$transactionNr][$filePath])) {
                $result = false;
            }
            if (!is_null($result)) {
                break;
            }
        }

        if (is_null($result)) {
            /* @var $filesystem FilesystemInterface */
            $filesystem = $this->filesystem;

            $result = $filesystem->fileExists($filePath);
        }

        return $result;
    }

    public function getFilesInDir($path)
    {
        /* @var $filesystem FilesystemInterface */
        $filesystem = $this->filesystem;

        $result = $filesystem->getFilesInDir($path);

        if ($path[strlen($path)-1] !== '/') {
            $path = "{$path}/";
        }

        foreach ($this->filesStack as $transactionNr => $files) {
            foreach (array_keys($files) as $filePath) {
                if (substr($filePath, 0, strlen($path)) === $path) {
                    $fileName = substr($filePath, strlen($path));
                    if (false !== strpos($fileName, "/")) {
                        $fileName = substr($fileName, 0, strpos($fileName, "/"));
                    }
                    $result[] = $fileName;
                }
            }
            $deletedNames = array();
            $deletedPaths = array_keys($this->deletedFilepathsStack[$transactionNr]);
            foreach ($deletedPaths as $filePath) {
                if (substr($filePath, 0, strlen($path)) === $path) {
                    $deletedNames[] = substr($filePath, strlen($path));
                }
            }
            $result = array_diff($result, $deletedNames);
        }

        return array_values(array_unique($result));
    }

    /**
     * @return DirectoryIterator
     */
    public function getDirectoryIterator($path)
    {
        return new InmemoryDirectoryIterator($path, $this);
    }

    ### TRANSACTION-INTERFACE

    public function beginTransaction($withConsistentSnapshot = false, $readOnly = false)
    {
        $this->createdFilepathsStack[] = array();
        $this->deletedFilepathsStack[] = array();
        $this->filesStack[] = array();
        $this->directoriesStack[] = array();
    }

    public function commit()
    {
        /* @var $filesystem FilesystemInterface */
        $filesystem = $this->filesystem;

        if (count($this->filesStack) <= 1) {
            throw new ErrorException("Tried to commit without transaction!");
        }

        $createdFiles = array_pop($this->createdFilepathsStack);
        $deletedFiles = array_pop($this->deletedFilepathsStack);

        if (count($this->createdFilepathsStack) > 1) {
            end($this->createdFilepathsStack);
            foreach ($createdFiles as $filePath) {
                $this->createdFilepathsStack[key($this->createdFilepathsStack)][$filePath] = $filePath;
            }

        } else {
            foreach ($createdFiles as $filePath) {
                /* @var $realFile FileInterface */
                $realFile = $filesystem->getFile($filePath, "w");

                /* @var $file TransactionalFile */
                $file = null;

                foreach (array_reverse($this->filesStack) as $files) {
                    if (isset($files[$filePath])) {
                        $file = $files[$filePath];
                    }
                }

                $realFile->setData($file->getData());
            }
        }

        $files = array_pop($this->filesStack);
        end($this->filesStack);

        foreach ($files as $filePath => $file) {
            /* @var $file TransactionalFile */

            $file->commit();

            $this->filesStack[key($this->filesStack)][$filePath] = $file;
        }

        if (count($this->deletedFilepathsStack) > 1) {
            end($this->deletedFilepathsStack);
            foreach ($deletedFiles as $filePath) {
                $this->deletedFilepathsStack[key($this->deletedFilepathsStack)][$filePath] = $filePath;
            }

        } else {
            foreach ($deletedFiles as $filePath) {
                $filesystem->fileUnlink($filePath);
            }
        }

        array_pop($this->directoriesStack);
    }

    public function rollback()
    {
        if (count($this->filesStack) <= 1) {
            throw new ErrorException("Tried to rollback without transaction!");
        }

        array_pop($this->directoriesStack);
        array_pop($this->createdFilepathsStack);
        array_pop($this->deletedFilepathsStack);

        foreach (array_pop($this->filesStack) as $filePath => $file) {
            /* @var $file TransactionalFile */

            $file->rollback();
        }
    }

}
