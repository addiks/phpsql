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

    public function __construct(
        FileInterface $file,
        FileInterface $transactionStorage = null
    ) {
        if (is_null($transactionStorage)) {
            $transactionStorage = new FileResourceProxy(fopen("php://memory", "w"));
        }

        $this->file = $file;
        $this->transactionStorage = $transactionStorage;
    }

    protected $file;

    protected $transactionStorage;

    protected $wasClosed = false;

    const PAGE_SIZE = 16384;

    protected $seekPosition;

    protected $fileSize;

    protected $pageMap = array();

    protected $isInTransaction = false;

    protected function loadPage($pageIndex)
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        $file->seek($pageIndex * self::PAGE_SIZE);

        $pageData = $file->read(self::PAGE_SIZE);
        $pageData = str_pad($pageData, self::PAGE_SIZE, "\0", STR_PAD_LEFT);

        $transactionStorage->seek(0, SEEK_END);

        $transactionStorageIndex = $transactionStorage->tell() / self::PAGE_SIZE;

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

        $transactionStorage->seek($transactionStorageIndex * self::PAGE_SIZE);
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

            $positionInPage = $this->seekPosition % self::PAGE_SIZE;
            $pageIndex = ($this->seekPosition-$positionInPage) / self::PAGE_SIZE;

            $pageData = substr($data, 0, self::PAGE_SIZE);
            $data = substr($data, self::PAGE_SIZE);

            $this->seekToPage($pageIndex);

            $transactionStorage->seek($positionInPage, SEEK_CUR);
            $transactionStorage->write($pageData);

            while (strlen($data) > 0) {
                $pageIndex++;

                $pageData = substr($data, 0, self::PAGE_SIZE);
                $data = substr($data, self::PAGE_SIZE);

                $this->seekToPage($pageIndex);

                $transactionStorage->write($pageData);

                $lastWrittenPosition = ($pageIndex * self::PAGE_SIZE) + strlen($pageData);

                $this->seekPosition = $lastWrittenPosition;
                if ($lastWrittenPosition > $this->fileSize) {
                    $this->fileSize = $lastWrittenPosition;
                }
            }

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

            $positionInPage = $this->seekPosition % self::PAGE_SIZE;
            $firstPageIndex = ($this->seekPosition-$positionInPage) / self::PAGE_SIZE;

            $endSeekPosition = $this->seekPosition += $length;

            $positionInLastPage = $endSeekPosition % self::PAGE_SIZE;
            $lastPageIndex = ($endSeekPosition-$positionInLastPage) / self::PAGE_SIZE;

            # first page
            if (isset($this->pageMap[$firstPageIndex])) {
                $this->seekToPage($firstPageIndex);
                $transactionStorage->seek($positionInPage, SEEK_CUR);
                $result .= $transactionStorage->read(self::PAGE_SIZE - $positionInPage);

            } else {
                $file->seek($firstPageIndex * self::PAGE_SIZE);
                $result .= $file->read(self::PAGE_SIZE - $positionInPage);
            }

            # middle pages
            for ($pageIndex = $firstPageIndex+1; $pageIndex < $lastPageIndex; $pageIndex++) {
                if (isset($this->pageMap[$pageIndex])) {
                    $this->seekToPage($pageIndex);
                    $result .= $transactionStorage->read(self::PAGE_SIZE);

                } else {
                    $file->seek($pageIndex * self::PAGE_SIZE);
                    $result .= $file->read(self::PAGE_SIZE);
                }
            }

            # last page
            if ($lastPageIndex > $firstPageIndex) {
                if (isset($this->pageMap[$lastPageIndex])) {
                    $this->seekToPage($lastPageIndex);
                    $transactionStorage->seek($positionInLastPage, SEEK_CUR);
                    $result .= $transactionStorage->read(self::PAGE_SIZE - $positionInLastPage);

                } else {
                    $file->seek($lastPageIndex * self::PAGE_SIZE);
                    $result .= $file->read(self::PAGE_SIZE - $positionInPage);
                }
            }

            $this->seekPosition += strlen($result);

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

            $lastPageIndex = floor($size / self::PAGE_SIZE);
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
            $transactionStorage->truncate(($lastTransitionStorageIndex+1) * self::PAGE_SIZE);

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->truncate($size);
        }
    }

    public function seek($offset, $seekMode = SEEK_SET)
    {
        if ($this->isInTransaction) {
            $this->seekPosition = $offset;

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
            $result = $this->seekPosition >= $this->fileSize;

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
            # TODO

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->lock($mode);
        }

        return $result;
    }

    public function flush()
    {
        $result = null;

        if ($this->isInTransaction) {
            # TODO

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->flush();
        }

        return $result;
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
        $result = null;

        if ($this->isInTransaction) {
            # TODO

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
            # TODO

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
            # TODO

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
            # TODO

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
        $this->rollback();
        $this->isInTransaction = true;
    }

    public function commit()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;

        foreach ($this->pageMap as $pageIndex => $transactionStorageIndex) {
            $this->seekToPage($pageIndex);
            $pageData = $transactionStorage->read(self::PAGE_SIZE);

            $file->seek($pageIndex * self::PAGE_SIZE);
            $file->write($pageData);
        }

        $file->seek($this->seekPosition);

        $this->rollback();
    }

    public function rollback()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorage;
        $transactionStorage->truncate(0);

        $this->pageMap = array();
        $this->seekPosition = $file->tell();
        $this->fileSize = $file->getLength();
        $this->isInTransaction = false;
    }

}
