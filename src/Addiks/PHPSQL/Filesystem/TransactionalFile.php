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

use Addiks\PHPSQL\Filesystem\FileInterface;
use Addiks\PHPSQL\Iterators\TransactionalInterface;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class TransactionalFile implements FileInterface, TransactionalInterface
{

    const PAGE_SIZE_MINIMUM = 4;

    public function __construct(
        FileInterface $file,
        FileInterface $transactionStorage = null,
        $pageSize = 16384
    ) {
        if (is_null($transactionStorage)) {
            $transactionStorage = new FileResourceProxy(fopen("php://memory", "w"));
        }

        $this->file = $file;
        $this->transactionStorage = $transactionStorage;
        $this->pageSize = (int)$pageSize;

        if ($this->pageSize < self::PAGE_SIZE_MINIMUM) {
            $this->pageSize = self::PAGE_SIZE_MINIMUM;
        }
    }

    protected $file;

    protected $transactionStorage;

    protected $wasClosed = false;

    protected $pageSize;

    protected $seekPosition;

    protected $fileSize;

    protected $pageMap = array();

    protected $isInTransaction = false;

    protected $fileSeekBeforeTransaction = 0;

    protected $isLocked = false;

    protected function loadPage($pageIndex)
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        $fileSeek = min($file->getLength(), $pageIndex * $this->pageSize);

        $pageData = "";
        if ($file->getLength() > $fileSeek) {
            $file->seek($fileSeek);
            $readLength = min($this->pageSize, $file->getLength() - $file->tell());
            $pageData = $file->read($readLength);
        }

        $pageData = str_pad($pageData, $this->pageSize, "\0", STR_PAD_RIGHT);

        $transactionStorage->seek(0, SEEK_END);

        $transactionStorageIndex = $transactionStorage->tell() / $this->pageSize;

        $transactionStorage->write($pageData);

        $this->pageMap[$pageIndex] = $transactionStorageIndex;
    }

    protected function seekToPage($pageIndex)
    {
        if (!isset($this->pageMap[$pageIndex])) {
            $this->loadPage($pageIndex);
        }

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        $transactionStorageIndex = $this->pageMap[$pageIndex];

        $transactionStorage->seek($transactionStorageIndex * $this->pageSize);
    }

    ### FILE-INTERFACE

    public function close()
    {
        if ($this->isInTransaction) {
            $this->wasClosed = true;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->close();
        }
    }

    public function write($data)
    {
        /* @var $file FileInterface */
        $file = $this->file;

        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            $positionInPage = $this->seekPosition % $this->pageSize;
            $pageIndex = ($this->seekPosition-$positionInPage) / $this->pageSize;

            do {
                $pageData = substr($data, 0, $this->pageSize - $positionInPage);
                $data = substr($data, $this->pageSize - $positionInPage);

                $this->seekToPage($pageIndex);

                $transactionStorage->seek($positionInPage, SEEK_CUR);
                $transactionStorage->write($pageData);

                $lastWrittenPosition = ($pageIndex * $this->pageSize) + strlen($pageData) + $positionInPage;

                $this->seekPosition = $lastWrittenPosition;
                if ($lastWrittenPosition > $this->fileSize) {
                    $this->fileSize = $lastWrittenPosition;
                }

                $positionInPage = 0;
                $pageIndex++;
            } while (strlen($data) > 0);

        } else {
            $file->write($data);
        }
    }

    public function read($length)
    {
        $result = "";

        /* @var $file FileInterface */
        $file = $this->file;

        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            $length = min($length, $this->fileSize - $this->seekPosition);

            if ($length > 0) {
                $positionInPage = $this->seekPosition % $this->pageSize;
                $firstPageIndex = ($this->seekPosition-$positionInPage) / $this->pageSize;

                $endSeekPosition = $this->seekPosition + $length;

                $positionInLastPage = $endSeekPosition % $this->pageSize;
                $lastPageIndex = ($endSeekPosition-$positionInLastPage) / $this->pageSize;

                # first page
                if (isset($this->pageMap[$firstPageIndex])) {
                    $this->seekToPage($firstPageIndex);
                    $transactionStorage->seek($positionInPage, SEEK_CUR);
                    $result .= $transactionStorage->read($this->pageSize - $positionInPage);

                } else {
                    $file->seek($firstPageIndex * $this->pageSize);
                    $result .= $file->read($this->pageSize - $positionInPage);
                }

                # middle pages
                for ($pageIndex = $firstPageIndex+1; $pageIndex < $lastPageIndex; $pageIndex++) {
                    if (isset($this->pageMap[$pageIndex])) {
                        $this->seekToPage($pageIndex);
                        $result .= $transactionStorage->read($this->pageSize);

                    } else {
                        $file->seek($pageIndex * $this->pageSize);
                        $result .= $file->read($this->pageSize);
                    }
                }

                # last page
                if ($lastPageIndex > $firstPageIndex) {
                    if (isset($this->pageMap[$lastPageIndex])) {
                        $this->seekToPage($lastPageIndex);
                        #$transactionStorage->seek($positionInLastPage, SEEK_CUR);
                        $result .= $transactionStorage->read($positionInLastPage);

                    } else {
                        $file->seek($lastPageIndex * $this->pageSize);
                        $result .= $file->read($this->pageSize - $positionInPage);
                    }
                }

                $result = substr($result, 0, $length);

                $this->seekPosition += strlen($result);
            }

        } else {
            $result = $file->read($length);
        }

        return $result;
    }

    public function truncate($size)
    {
        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            $lastPageIndex = floor($size / $this->pageSize);
            $lastTransitionStorageIndex = 0;
            foreach ($this->pageMap as $pageIndex => $transactionStorageIndex) {
                if ($pageIndex > $lastPageIndex) {
                    unset($this->pageMap[$pageIndex]);
                    if ($lastTransitionStorageIndex < $transactionStorageIndex) {
                        $lastTransitionStorageIndex = $transactionStorageIndex;
                    }
                }
            }

            $this->fileSize = $size;
            $transactionStorage->truncate(($lastTransitionStorageIndex+1) * $this->pageSize);

            if ($this->seekPosition > $this->fileSize) {
                $this->seekPosition = $this->fileSize;
            }

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->truncate($size);
        }
    }

    public function seek($offset, $seekMode = SEEK_SET)
    {
        if ($this->isInTransaction) {
            if ($seekMode === SEEK_SET) {
                $this->seekPosition = $offset;

            } elseif ($seekMode === SEEK_CUR) {
                $this->seekPosition += $offset;

            } elseif ($seekMode === SEEK_END) {
                $this->seekPosition = $this->fileSize + $offset;
            }

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->seek($offset, $seekMode);
        }
    }

    public function tell()
    {
        $result = null;

        if ($this->isInTransaction) {
            $result = $this->seekPosition;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->tell();
        }

        return $result;
    }

    public function eof()
    {
        $result = null;

        if ($this->isInTransaction) {
            $result = $this->seekPosition > $this->fileSize;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->eof();
        }

        return $result;
    }

    public function lock($mode)
    {
        $result = null;

        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            $transactionStorage->lock($mode);

            if (in_array($mode, [LOCK_EX, LOCK_SH])) {
                $this->isLocked = true;
            } else {
                $this->isLocked = false;
            }

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->lock($mode);
        }

        return $result;
    }

    public function flush()
    {
        if (!$this->isInTransaction) {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->flush();
        }
    }

    public function getSize()
    {
        $result = null;

        if ($this->isInTransaction) {
            $result = $this->fileSize;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->getSize();
        }

        return $result;
    }

    public function readLine()
    {
        $result = "";

        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            /* @var $file FileInterface */
            $file = $this->file;

            $positionInPage = $this->seekPosition % $this->pageSize;
            $pageIndex = ($this->seekPosition-$positionInPage) / $this->pageSize;

            do {
                if (isset($this->pageMap[$pageIndex])) {
                    $this->seekToPage($pageIndex);

                    $transactionStorage->seek($positionInPage);

                    $line = $transactionStorage->readLine();
                    $line = substr($line, 0, $this->pageSize);

                } else {
                    $file->seek($this->seekPosition);

                    $line = $file->readLine();
                    $line = substr($line, 0, $this->pageSize);

                    $file->seek($this->fileSeekBeforeTransaction);
                }

                $this->seekPosition += strlen($line);

                $result .= $line;

                $pageIndex += 1;
                $positionInPage = 0;
            } while (strpos($result, "\n") === false && strlen($line)>0 && $this->seekPosition < $this->fileSize);


        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->readLine();
        }

        return $result;
    }

    public function getData()
    {
        $result = null;

        if ($this->isInTransaction) {
            $beforeSeekPosition = $this->seekPosition;

            $this->seekPosition = 0;
            $result = $this->read($this->fileSize);

            $this->seekPosition = $beforeSeekPosition;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->getData();
        }

        return $result;
    }

    public function setData($data)
    {
        $result = null;

        if ($this->isInTransaction) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = $this->transactionStorage;

            $this->pageMap = array();
            $this->fileSize = strlen($data);
            $this->seekPosition = 0;
            $transactionStorage->truncate(0);

            $pageIndex = 0;
            while (strlen($data) > 0) {
                $pageData = substr($data, 0, $this->pageSize);
                $data = substr($data, strlen($pageData));
                $this->pageMap[$pageIndex] = $pageIndex;
                $transactionStorage->write($pageData);
                $pageIndex++;
            }

            $transactionStorage->seek(0);

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->setData($data);
        }

        return $result;
    }

    public function addData($data)
    {
        $result = null;

        if ($this->isInTransaction) {
            $this->seek(0, SEEK_END);
            $this->write($data);

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->addData($data);
        }

        return $result;
    }

    public function getLength()
    {
        $result = null;

        if ($this->isInTransaction) {
            $result = $this->fileSize;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->getLength();
        }

        return $result;
    }

    ### TRANSACTION-INTERFACE

    public function beginTransaction($withConsistentSnapshot = false, $readOnly = false)
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        $this->fileSeekBeforeTransaction = $file->tell();

        $this->rollback();
        $this->isInTransaction = true;

        $file->lock(LOCK_EX);

        if ($this->isLocked) {
            $transactionStorage->lock(LOCK_EX);
        }
    }

    public function commit()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        foreach ($this->pageMap as $pageIndex => $transactionStorageIndex) {
            $this->seekToPage($pageIndex);
            $pageData = $transactionStorage->read($this->pageSize);

            $file->seek($pageIndex * $this->pageSize);
            $file->write($pageData);
        }

        $file->truncate($this->fileSize);
        $file->seek($this->seekPosition);
        $file->flush();

        $this->fileSeekBeforeTransaction = $this->seekPosition;

        $this->rollback();
    }

    public function rollback()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;
        $transactionStorage->truncate(0);

        $file->seek($this->fileSeekBeforeTransaction);

        $this->pageMap = array();
        $this->seekPosition = $file->tell();
        $this->fileSize = $file->getLength();
        $this->isInTransaction = false;

        if ($this->isLocked) {
            $transactionStorage->lock(LOCK_UN);

        } else {
            $file->lock(LOCK_UN);
        }
    }

}
