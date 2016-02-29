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

    protected $fileSeekBeforeTransaction;

    protected $seekPositionStack = array();

    protected $fileSizeStack = array();

    protected $pageMapStack = array();

    protected $wasClosed = false;

    protected $pageSize;

    protected $isLocked = false;

    protected function loadPage($pageIndex, $transactionNr = null)
    {
        if (is_null($transactionNr)) {
            end($this->pageMapStack);
            $transactionNr = key($this->pageMapStack);
        }

        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorageStack[$transactionNr];

        $foundInTransaction = false;

        end($this->pageMapStack);
        do {
            $checkingTransactionNr = key($this->pageMapStack);
            if (!is_null($checkingTransactionNr)) {
                if (isset($this->pageMapStack[$checkingTransactionNr][$pageIndex])) {
                    $transactionStorageIndex = $this->pageMapStack[$checkingTransactionNr][$pageIndex];

                    /* @var $transactionStorage FileInterface */
                    $sourceTransactionStorage = $this->transactionStorageStack[$checkingTransactionNr];
                    $sourceTransactionStorage->seek($transactionStorageIndex * $this->pageSize);

                    $pageData = $sourceTransactionStorage->read($this->pageSize);
                    $foundInTransaction = true;
                    break;
                }
            }
            prev($this->pageMapStack);
        } while (!is_null($checkingTransactionNr));
        end($this->pageMapStack);

        if (!$foundInTransaction) {
            $fileSeek = min($file->getLength(), $pageIndex * $this->pageSize);

            $pageData = "";
            if ($file->getLength() > $fileSeek) {
                $file->seek($fileSeek);
                $readLength = min($this->pageSize, $file->getLength() - $file->tell());
                $pageData = $file->read($readLength);
            }
        }

        $pageData = str_pad($pageData, $this->pageSize, "\0", STR_PAD_RIGHT);

        $transactionStorage->seek(0, SEEK_END);

        $transactionStorageIndex = $transactionStorage->tell() / $this->pageSize;

        $transactionStorage->write($pageData);

        $this->pageMapStack[$transactionNr][$pageIndex] = $transactionStorageIndex;
    }

    protected function seekToPage($pageIndex, $transactionNr = null)
    {
        if (is_null($transactionNr)) {
            end($this->pageMapStack);
            $transactionNr = key($this->pageMapStack);
        }

        if (!isset($this->pageMapStack[$transactionNr][$pageIndex])) {
            $this->loadPage($pageIndex, $transactionNr);
        }

        /* @var $transactionStorage FileInterface */
        $transactionStorage = $this->transactionStorageStack[$transactionNr];

        $transactionStorageIndex = $this->pageMapStack[$transactionNr][$pageIndex];

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

            $positionInPage = end($this->seekPositionStack) % $this->pageSize;
            $pageIndex = (end($this->seekPositionStack)-$positionInPage) / $this->pageSize;

            do {
                $pageData = substr($data, 0, $this->pageSize - $positionInPage);
                $data = substr($data, $this->pageSize - $positionInPage);

                $this->seekToPage($pageIndex);

                $transactionStorage->seek($positionInPage, SEEK_CUR);
                $transactionStorage->write($pageData);

                $lastWrittenPosition = ($pageIndex * $this->pageSize) + strlen($pageData) + $positionInPage;
end($this->seekPositionStack);
                $this->seekPositionStack[key($this->seekPositionStack)] = $lastWrittenPosition;
                if ($lastWrittenPosition > end($this->fileSizeStack)) {
                    $this->fileSizeStack[key($this->fileSizeStack)] = $lastWrittenPosition;
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

            $length = min($length, end($this->fileSizeStack) - end($this->seekPositionStack));

            if ($length > 0) {
                $positionInPage = end($this->seekPositionStack) % $this->pageSize;
                $firstPageIndex = (end($this->seekPositionStack)-$positionInPage) / $this->pageSize;

                $endSeekPosition = end($this->seekPositionStack) + $length;

                $positionInLastPage = $endSeekPosition % $this->pageSize;
                $lastPageIndex = ($endSeekPosition-$positionInLastPage) / $this->pageSize;

                end($this->pageMapStack);

                # first page
                $pageFound = false;
                foreach (array_reverse($this->pageMapStack, true) as $transactionNr => $pageMap) {
                    if (isset($pageMap[$firstPageIndex])) {
                        $this->seekToPage($firstPageIndex, $transactionNr);

                        /* @var $transactionStorage FileInterface */
                        $transactionStorage = $this->transactionStorageStack[$transactionNr];
                        $result .= $transactionStorage->read($this->pageSize - $positionInPage);
                        $pageFound = true;
                        break;
                    }
                }
                if (!$pageFound) {
                    $file->seek($firstPageIndex * $this->pageSize);
                    $result .= $file->read($this->pageSize - $positionInPage);
                }

                # middle pages
                for ($pageIndex = $firstPageIndex+1; $pageIndex < $lastPageIndex; $pageIndex++) {
                    $pageFound = false;
                    foreach (array_reverse($this->pageMapStack, true) as $transactionNr => $pageMap) {
                        if (isset($pageMap[$pageIndex])) {
                            $this->seekToPage($pageIndex, $transactionNr);

                            /* @var $transactionStorage FileInterface */
                            $transactionStorage = $this->transactionStorageStack[$transactionNr];
                            $result .= $transactionStorage->read($this->pageSize);
                            $pageFound = true;
                            break;
                        }
                    }
                    if (!$pageFound) {
                        $file->seek($pageIndex * $this->pageSize);
                        $result .= $file->read($this->pageSize);
                    }
                }

                # last page
                if ($lastPageIndex > $firstPageIndex) {
                    $pageFound = false;
                    foreach (array_reverse($this->pageMapStack, true) as $transactionNr => $pageMap) {
                        if (isset($pageMap[$lastPageIndex])) {
                            $this->seekToPage($lastPageIndex, $transactionNr);

                            /* @var $transactionStorage FileInterface */
                            $transactionStorage = $this->transactionStorageStack[$transactionNr];
                            $result .= $transactionStorage->read($positionInLastPage);
                            $pageFound = true;
                            break;
                        }
                    }
                    if (!$pageFound) {
                        $file->seek($lastPageIndex * $this->pageSize);
                        $result .= $file->read($positionInLastPage);
                    }
                }

                $result = substr($result, 0, $length);

                $this->seekPositionStack[key($this->seekPositionStack)] += strlen($result);

                $file->seek($this->fileSeekBeforeTransaction);
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

            end($this->fileSizeStack);
            $this->fileSizeStack[key($this->fileSizeStack)] = $size;
            $transactionStorage->truncate(($lastTransitionStorageIndex+1) * $this->pageSize);

            if (end($this->seekPositionStack) > end($this->fileSizeStack)) {
                $this->seekPositionStack[key($this->seekPositionStack)] = end($this->fileSizeStack);
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
            end($this->seekPositionStack);
            if ($seekMode === SEEK_SET) {
                $this->seekPositionStack[key($this->seekPositionStack)] = $offset;

            } elseif ($seekMode === SEEK_CUR) {
                $this->seekPositionStack[key($this->seekPositionStack)] += $offset;

            } elseif ($seekMode === SEEK_END) {
                $this->seekPositionStack[key($this->seekPositionStack)] = end($this->fileSizeStack) + $offset;
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
            $result = end($this->seekPositionStack);

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
            $result = end($this->seekPositionStack) > end($this->fileSizeStack);

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
            $result = end($this->fileSizeStack);

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
            /* @var $file FileInterface */
            $file = $this->file;

            $positionInPage = end($this->seekPositionStack) % $this->pageSize;
            $pageIndex = (end($this->seekPositionStack)-$positionInPage) / $this->pageSize;

            end($this->pageMapStack);

            do {
                $foundInTransaction = false;
                foreach (array_reverse($this->pageMapStack, true) as $transactionNr => $pageMap) {
                    if (isset($pageMap[$pageIndex])) {
                        $this->seekToPage($pageIndex, $transactionNr);

                        /* @var $transactionStorage FileInterface */
                        $transactionStorage = $this->transactionStorageStack[$transactionNr];
                        $transactionStorage->seek($positionInPage, SEEK_CUR);

                        $line = $transactionStorage->readLine();
                        $line = substr($line, 0, $this->pageSize - $positionInPage);
                        $foundInTransaction = true;
                        break;
                    }
                }
                if (!$foundInTransaction) {
                    $file->seek(end($this->seekPositionStack));
                    $line = $file->readLine();
                    $line = substr($line, 0, $this->pageSize);
                }

                if (strlen($line) + end($this->seekPositionStack) > end($this->fileSizeStack)) {
                    $line = substr($line, 0, end($this->fileSizeStack) - end($this->seekPositionStack));
                }

                $this->seekPositionStack[key($this->seekPositionStack)] += strlen($line);

                $result .= $line;

                $pageIndex += 1;
                $positionInPage = 0;
            } while (strpos($result, "\n") === false && strlen($line)>0
             && end($this->seekPositionStack) < end($this->fileSizeStack));

            $file->seek($this->fileSeekBeforeTransaction);

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
            $beforeSeekPosition = end($this->seekPositionStack);

            $this->seekPositionStack[key($this->seekPositionStack)] = 0;
            $result = $this->read(end($this->fileSizeStack));

            end($this->seekPositionStack);
            $this->seekPositionStack[key($this->seekPositionStack)] = $beforeSeekPosition;

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
            end($this->fileSizeStack);
            end($this->seekPositionStack);

            $this->pageMapStack[key($this->pageMapStack)] = array();
            $this->fileSizeStack[key($this->fileSizeStack)] = strlen($data);
            $this->seekPositionStack[key($this->seekPositionStack)] = 0;
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
            $result = end($this->fileSizeStack);

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

        if (count($this->transactionStorageStack) <= 0) {
            $this->fileSeekBeforeTransaction = $file->tell();
            $this->seekPositionStack[] = $file->tell();
            $this->fileSizeStack[] = $file->getLength();

            $file->lock(LOCK_EX);

        } else {
            $this->seekPositionStack[] = end($this->seekPositionStack);
            $this->fileSizeStack[] = end($this->fileSizeStack);
        }

        $this->transactionStorageStack[] = $transactionStorage;
        $this->pageMapStack[] = array();

        $transactionStorage->truncate(0);

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

        /* @var $transactionStorage FileInterface */
        $transactionStorage = end($this->transactionStorageStack);

        /* @var $targetTransactionStorage FileInterface */
        $targetTransactionStorage = null;

        $targetTransactionNr = null;
        if (count($this->transactionStorageStack) > 1) {
            $targetTransactionNr = count($this->transactionStorageStack) - 2;
            $targetTransactionStorage = $this->transactionStorageStack[$targetTransactionNr];
        }

        end($this->pageMapStack);
        foreach ($this->pageMapStack[key($this->pageMapStack)] as $pageIndex => $transactionStorageIndex) {
            $this->seekToPage($pageIndex);
            $pageData = $transactionStorage->read($this->pageSize);
            $pageData = str_pad($pageData, $this->pageSize, "\0", STR_PAD_RIGHT);

            if (count($this->transactionStorageStack) === 1) {
                $file->seek($pageIndex * $this->pageSize);
                $file->write($pageData);

            } else {
                $this->seekToPage($pageIndex, $targetTransactionNr);
                $targetTransactionStorage->write($pageData);
            }
        }

        $this->fileSeekBeforeTransaction = null;

        if (count($this->transactionStorageStack) === 1) {
            $file->truncate(end($this->fileSizeStack));
            $file->flush();

            $file->seek(end($this->seekPositionStack));

            if (!$this->isLocked) {
                $file->lock(LOCK_UN);
            }
        }

        $fileSize = array_pop($this->fileSizeStack);
        $fileSeek = array_pop($this->seekPositionStack);
        array_pop($this->transactionStorageStack);
        array_pop($this->pageMapStack);

        end($this->fileSizeStack);
        $this->fileSizeStack[key($this->fileSizeStack)] = $fileSize;

        end($this->seekPositionStack);
        $this->seekPositionStack[key($this->seekPositionStack)] = $fileSeek;

        $transactionStorage->close();
    }

    public function rollback()
    {
        /* @var $file FileInterface */
        $file = $this->file;

        /* @var $transactionStorage FileInterface */
        $transactionStorage = array_pop($this->transactionStorageStack);

        $file->seek($this->fileSeekBeforeTransaction);

        array_pop($this->pageMapStack);
        array_pop($this->fileSizeStack);
        array_pop($this->seekPositionStack);

        if (!$this->isLocked) {
            $file->lock(LOCK_UN);
        }

        $transactionStorage->close();
    }

}
