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
use Addiks\PHPSQL\Filesystem\FileInterface;
use Addiks\PHPSQL\Iterators\TransactionalInterface;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class TransactionalFile implements FileInterface, TransactionalInterface
{

    const PAGE_SIZE_MINIMUM = 4;

    public function __construct(
        FileInterface $file,
        $pageSize = 16384
    ) {
        $this->file = $file;
        $this->pageSize = (int)$pageSize;

        if ($this->pageSize < self::PAGE_SIZE_MINIMUM) {
            $this->pageSize = self::PAGE_SIZE_MINIMUM;
        }
    }

    protected $file;

    protected $transactionStorageStack = array();

    protected $fileSeekBeforeTransactionStack = array();

    protected $wasClosed = false;

    protected $pageSize;

    protected $seekPosition;

    protected $fileSize;

    protected $pageMapStack = array();

    protected $isLocked = false;

    protected function loadPage($pageIndex)
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = end($this->transactionStorageStack);

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

        end($this->pageMapStack);
        $this->pageMapStack[key($this->pageMapStack)][$pageIndex] = $transactionStorageIndex;
    }

    protected function seekToPage($pageIndex)
    {
        end($this->pageMapStack);
        if (!isset($this->pageMapStack[key($this->pageMapStack)][$pageIndex])) {
            $this->loadPage($pageIndex);
        }

        /* @var $transactionStorage FileInterface */
        $transactionStorage = end($this->transactionStorageStack);

        $transactionStorageIndex = $this->pageMapStack[key($this->pageMapStack)][$pageIndex];

        $transactionStorage->seek($transactionStorageIndex * $this->pageSize);
    }

    ### FILE-INTERFACE

    public function close()
    {
        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

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

        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

            $length = min($length, $this->fileSize - $this->seekPosition);

            if ($length > 0) {
                $positionInPage = $this->seekPosition % $this->pageSize;
                $firstPageIndex = ($this->seekPosition-$positionInPage) / $this->pageSize;

                $endSeekPosition = $this->seekPosition + $length;

                $positionInLastPage = $endSeekPosition % $this->pageSize;
                $lastPageIndex = ($endSeekPosition-$positionInLastPage) / $this->pageSize;

                end($this->pageMapStack);

                # first page
                if (isset($this->pageMapStack[key($this->pageMapStack)][$firstPageIndex])) {
                    $this->seekToPage($firstPageIndex);
                    $transactionStorage->seek($positionInPage, SEEK_CUR);
                    $result .= $transactionStorage->read($this->pageSize - $positionInPage);

                } else {
                    $file->seek($firstPageIndex * $this->pageSize);
                    $result .= $file->read($this->pageSize - $positionInPage);
                }

                # middle pages
                for ($pageIndex = $firstPageIndex+1; $pageIndex < $lastPageIndex; $pageIndex++) {
                    if (isset($this->pageMapStack[key($this->pageMapStack)][$pageIndex])) {
                        $this->seekToPage($pageIndex);
                        $result .= $transactionStorage->read($this->pageSize);

                    } else {
                        $file->seek($pageIndex * $this->pageSize);
                        $result .= $file->read($this->pageSize);
                    }
                }

                # last page
                if ($lastPageIndex > $firstPageIndex) {
                    if (isset($this->pageMapStack[key($this->pageMapStack)][$lastPageIndex])) {
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

                $file->seek(end($this->fileSeekBeforeTransactionStack));
            }

        } else {
            $result = $file->read($length);
        }

        return $result;
    }

    public function truncate($size)
    {
        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

            end($this->pageMapStack);

            $lastPageIndex = floor($size / $this->pageSize);
            $lastTransitionStorageIndex = 0;
            foreach ($this->pageMapStack[key($this->pageMapStack)] as $pageIndex => $transactionStorageIndex) {
                if ($pageIndex > $lastPageIndex) {
                    unset($this->pageMapStack[key($this->pageMapStack)][$pageIndex]);
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
        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

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
        if (!count($this->transactionStorageStack) > 0) {
            /* @var $file FileInterface */
            $file = $this->file;

            $file->flush();
        }
    }

    public function getSize()
    {
        $result = null;

        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

            /* @var $file FileInterface */
            $file = $this->file;

            $positionInPage = $this->seekPosition % $this->pageSize;
            $pageIndex = ($this->seekPosition-$positionInPage) / $this->pageSize;

            end($this->pageMapStack);

            do {
                if (isset($this->pageMapStack[key($this->pageMapStack)][$pageIndex])) {
                    $this->seekToPage($pageIndex);

                    $transactionStorage->seek($positionInPage);

                    $line = $transactionStorage->readLine();
                    $line = substr($line, 0, $this->pageSize);

                } else {
                    $file->seek($this->seekPosition);
                    $line = $file->readLine();
                    $line = substr($line, 0, $this->pageSize);

                }

                $this->seekPosition += strlen($line);

                $result .= $line;

                $pageIndex += 1;
                $positionInPage = 0;
            } while (strpos($result, "\n") === false && strlen($line)>0 && $this->seekPosition < $this->fileSize);

            $file->seek(end($this->fileSeekBeforeTransactionStack));

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

        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
            /* @var $transactionStorage FileInterface */
            $transactionStorage = end($this->transactionStorageStack);

            end($this->pageMapStack);

            $this->pageMapStack[key($this->pageMapStack)] = array();
            $this->fileSize = strlen($data);
            $this->seekPosition = 0;
            $transactionStorage->truncate(0);

            $pageIndex = 0;
            while (strlen($data) > 0) {
                $pageData = substr($data, 0, $this->pageSize);
                $data = substr($data, strlen($pageData));
                $this->pageMapStack[key($this->pageMapStack)][$pageIndex] = $pageIndex;
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

        if (count($this->transactionStorageStack) > 0) {
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

        if (count($this->transactionStorageStack) > 0) {
            $result = $this->fileSize;

        } else {
            /* @var $file FileInterface */
            $file = $this->file;

            $result = $file->getLength();
        }

        return $result;
    }

    ### TRANSACTION-INTERFACE

    public function beginTransaction(
        $withConsistentSnapshot = false,
        $readOnly = false,
        FileInterface $transactionStorage = null
    ) {
        /* @var $file FileInterface */
        $file = $this->file;

        if (is_null($transactionStorage)) {
            $transactionStorage = new FileResourceProxy(fopen("php://memory", "w"));
        }

        $this->transactionStorageStack[] = $transactionStorage;

        $this->fileSeekBeforeTransactionStack[] = $file->tell();
        $this->seekPosition = $file->tell();
        $this->fileSize = $file->getLength();
        $this->pageMapStack[] = array();

        $transactionStorage->truncate(0);

        $file->lock(LOCK_EX);

        if ($this->isLocked) {
            $transactionStorage->lock(LOCK_EX);
        }
    }

    public function commit()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        if (count($this->pageMapStack) <= 0) {
            throw new ErrorException("Tried to commit without transaction!");
        }

        end($this->pageMapStack);

        /* @var $transactionStorage FileInterface */
        $transactionStorage = end($this->transactionStorageStack);

        foreach ($this->pageMapStack[key($this->pageMapStack)] as $pageIndex => $transactionStorageIndex) {
            $this->seekToPage($pageIndex);
            $pageData = $transactionStorage->read($this->pageSize);

            $file->seek($pageIndex * $this->pageSize);
            $file->write($pageData);
        }

        array_pop($this->transactionStorageStack);
        array_pop($this->fileSeekBeforeTransactionStack);

        $file->truncate($this->fileSize);
        $file->flush();

        $file->seek($this->seekPosition);

        array_pop($this->pageMapStack);

        $this->seekPosition = $file->tell();
        $this->fileSize = $file->getLength();

        if (!$this->isLocked) {
            $file->lock(LOCK_UN);
        }

        $transactionStorage->close();
    }

    public function rollback()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = array_pop($this->transactionStorageStack);

        $file->seek(array_pop($this->fileSeekBeforeTransactionStack));

        array_pop($this->pageMapStack);

        $this->seekPosition = $file->tell();
        $this->fileSize = $file->getLength();

        if (!$this->isLocked) {
            $file->lock(LOCK_UN);
        }

        $transactionStorage->close();
    }

}
