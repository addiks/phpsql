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

use Addiks\Protocol\Entity\Exception\Error;

use Addiks\Common\Entity;
use Addiks\Common\Value\Text\Filepath;

/**
 * A storage is a controlled item that allows you to persist data.
 *
 * @see Storages
 *
 * <p>
 * Each namespace has its own storages and can only access their owns.
 * If you want to make your storage public to other namespace,
 * you have to create an abstraction layer.
 * (for example a method in a resource returning the data or storage-entity)
 * </p>
 *
 * <p>
 * Unlike files, storages will be saved partionated on the disk.
 * For example, the storage "/foo/bar" in namespace Vendor\Module\Component will be saved under the path:
 *      dataFolder/Storages/f/e/8/6/3/3/Vendor-Module-Component/foo/bar
 *
 * (md5("/foo/bar") = "fe8633b9d82da8b3bff596647297998c")
 *
 * This allows you (or the system administrator) to partionate
 * subfolders into external places for load/space balancing.
 * </p>
 *
 * <p>
 * The storage-folder can get really big after some time in production,
 * and you (the sysadmin) may need to move a part of the storage to another physically storage (another HD).
 * This storage-system has a mirroring-mechanic built in which allows you to move storages while your system continues working.
 * Also, you can create perfect backups of the data-folder without interrupting the system.
 *
 * It works as following:
 *
 *  - First you choose which of the 6 one-character-folders you want to move somewhere else.
 *    That other place has to be a folder accassible on the local filesystem (e.g.: a mountpoint).
 *
 *  - Next you create a symlink from that target-folder to the one-char-folder with appendix ".mirror",
 *      "ln -sf /mnt/someMountPoint/external/folder/path /your/data/folder/Storages/f/e/8.mirror".
 *    (From now on the system will mirror every change made in /your/data/folder/Storages/f/e/8/* into that folder.)
 *
 *  - You copy the whole content from your local storage ('.../f/e/8/*') to that external mount-point.
 *    (While copying, the system will keep all already copied file up-to-date through the mirror)
 *    (Please keep in mind that the copy-process should not eat up all CPU-, HD- or I/O-power.)
 *
 *  - When the copy-process is finished, you remove the original one-char-folder and replace it with the mirror-symlink.
 *    That should optimally happen in an nearly atomic process:
 *       "cd /your/data/folder/Storages/f/e && mv 8 8.old && mv 8.mirror 8 && rm -rf 8.old"
 *
 *  - voilla, you moved a part of your storage to an external mountpoint without interrupting your service.
 *
 * If you are not familar with this method, you should test and experiment
 * with this a bit on a test-system before touching the productive.
 * </p>
 *
 * <p>
 * Storages use read and write locks (@see flock).
 * Please consider that when using storages.
 * Since you are the only one who can access your storages,
 * you have full control over the locking behaviour.
 * </p>
 */
class Storage extends Entity
{
    
    /**
     * Absolute path to storage-file.
     * @Column(type="string")
     * @var Filepath
     */
    private $filePath;
    
    /**
     * gets the absolute path to storage-file.
     * @return Filepath
     */
    public function getFilePath()
    {
        return $this->filePath;
    }
    
    public function getFileName()
    {
    
        return substr($this->getFilePath(), strrpos($this->getFilePath(), '/')+1);
    }
    
    private $symlinkFilePath;
    
    public function getSymLinkFilePath()
    {
        return $this->symlinkFilePath;
    }
    
    public function setSymLinkFilePath(Filepath $filePath)
    {
        $this->symlinkFilePath = $filePath;
    }
    
    private $realFilePath;
    
    public function setRealFilePath(Filepath $filePath)
    {
        $this->realFilePath;
    }
    
    public function getRealFilePath()
    {
        return $this->realFilePath;
    }
    
    /**
     * file-resurce-handle to storage-file.
     * @var resource
     */
    private $handle;
    
    /**
     * gets file-resurce-handle to storage-file.
     * @return resource
     * @throws Error
     */
    public function getHandle()
    {
        if (!is_resource($this->handle)) {
            $this->handle = fopen($this->getFilePath(), "r+");
        }
        return $this->handle;
    }
    
    /**
     * All data-mirror-starages to take care of.
     *
     * @var array
     */
    private $dataMirrors = array();
    
    /**
     * @see self::$dataMirrors
     * @param self $mirrorStorage
     */
    public function addDataMirror(self $mirrorStorage)
    {
        $this->dataMirrors[] = $mirrorStorage;
    }
    
    /**
     * @see self::$dataMirrors
     * @return array
     */
    public function getDataMirrors()
    {
        return $this->dataMirrors;
    }
    
    /**
     * Removes a data-mirror-storage from this storage.
     * @param self $mirrorStorage
     */
    public function removeDataMirror(self $mirrorStorage)
    {
        foreach ($this->dataMirrors as $index => $presentMirrorStorage) {
            if ($mirrorStorage === $presentMirrorStorage) {
                unset($this->dataMirrors[$index]);
            }
        }
    }
    
    /**
     * Constructor for specific storage-file.
     * @param string $filePath
     */
    public function __construct(Filepath $filePath)
    {
        parent::__construct();
    
        $this->filePath = $filePath;
    
        if (!file_exists((string)$this->getFilePath())) {
            if (!is_dir(dirname((string)$this->getFilePath()))) {
                mkdir(dirname($this->getFilePath()), 0770, true);
            }
            touch($this->getFilePath());
        }
        
    }
    
    
    /**
     * sets the stored data. overrides old data.
     * @param string $data
     */
    public function setData($data)
    {
        
        switch(true){
            case is_int($data):
            case is_float($data):
            case is_bool($data):
            case $data instanceof Entity:
            case $data instanceof Value:
                $data = (string)$data;
        }
        
        if (!is_string($data)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            throw new Error("Storage only accept string, float, int, bool, Value and Entity as content! (in {$trace[0]['file']} on line {$trace[0]['line']})");
        }
        
        flock($this->getHandle(), LOCK_EX); // exclusive write-lock
        
        fseek($this->getHandle(), 0, SEEK_SET);
        ftruncate($this->getHandle(), 0);
        
        $return = fwrite($this->getHandle(), $data);
        fflush($this->getHandle());
        
        flock($this->getHandle(), LOCK_UN);
        
        foreach ($this->getDataMirrors() as $mirrorStorage) {
            /* @var $mirrorStorage Storage */
            
            $mirrorStorage->setData($data);
        }
    }
    
    /**
     * appends data. old data persists preceeded to given data.
     * @param string $data
     * @param bool $autoFlush   should the data be written to disk immeadetly, otherwise call self::flush manually.
     * @see self::flush
     */
    public function addData($data, $autoFlush = true)
    {
        
        flock($this->getHandle(), LOCK_EX); // exclusive write-lock
        
        $position = ftell($this->getHandle());
        
        fseek($this->getHandle(), 0, SEEK_END);
        fwrite($this->getHandle(), $data);
        
        fseek($this->getHandle(), $position, SEEK_SET);
    
        if ($autoFlush) {
            fflush($this->getHandle());
        }
        
        flock($this->getHandle(), LOCK_UN); // non-blocking read-lock
        
        foreach ($this->getDataMirrors() as $mirrorStorage) {
            /* @var $mirrorStorage Storage */
                
            $mirrorStorage->addData($data, $autoFlush);
        }
    }
    
    /**
     * flushes queried data to disk.
     * @see self::addData
     */
    public function flush()
    {
        fflush($this->getHandle());
    }
    
    /**
     * gets the complete stored data at once.
     * Can be limited by length parameter.
     * @param int $length
     */
    public function getData($length = null)
    {
    
        fseek($this->getHandle(), 0, SEEK_SET);
    
        if (is_null($length)) {
            $length = $this->getLength();
        }
    
        if ($length<1) {
            return "";
        }
    
        $data = fread($this->getHandle(), $length);
    
        return $data;
    }
    
    /**
     * Clears (deletes) the currently saved data.
     */
    public function clear()
    {
        
        file_put_contents("/tmp/ga_debug.log", __LINE__ . "\n", FILE_APPEND);
        
        flock($this->getHandle(), LOCK_EX); // exclusive write-lock
        
        file_put_contents("/tmp/ga_debug.log", __LINE__ . "\n", FILE_APPEND);
        
        fseek($this->getHandle(), 0, SEEK_SET);
        ftruncate($this->getHandle(), 0);
    
        flock($this->getHandle(), LOCK_SH); // non-blocking read-lock
        
        foreach ($this->getDataMirrors() as $mirrorStorage) {
            /* @var $mirrorStorage Storage */
        
            $mirrorStorage->clear();
        }
    }
    
    /**
     * gets the length (bytes) of saved data in this storage.
     * @return int
     */
    public function getLength()
    {
    
        $currentPos = ftell($this->getHandle());
    
        fseek($this->getHandle(), 0, SEEK_END);
        $lastIndex = ftell($this->getHandle());
    
        fseek($this->getHandle(), $currentPos, SEEK_SET);
    
        return $lastIndex;
    }
    
    /**
     * Checks if there are data stored in this storage.
     * @return bool
     */
    public function hasDataStored()
    {
        return $this->getLength()!==0;
    }
    
    /**
     * is this storage-object destructed?
     * @var bool
     */
    private $destructed = false;
    
    /**
     * gets if this storage-object is destructed.
     * @return bool
     */
    public function getDestructed()
    {
        return $this->destructed;
    }
    
    /**
     * Defines whether this storage should be
     * removed when the PHP finishes.
     *
     * @var bool
     */
    private $isTemporary = false;
    
    public function setIsTemporary($bool)
    {
        $this->isTemporary = (bool)$bool;
    }
    
    public function getIsTemporary()
    {
        return $this->isTemporary;
    }
    
    /**
     * Closes (destructs) this storage.
     * Realeases all locks and closes the handle.
     *
     * After this this storage-entity cannot be used anymore.
     */
    public function close()
    {
        if ($this->destructed || !is_resource($this->handle)) {
            return;
        }
        flock($this->getHandle(), LOCK_UN); // unlock
        fclose($this->getHandle());
        $this->destructed = true;
        
    }
    
    /**
     * Destructor.
     * Makes sure that file gets unlocked, closed and marked as destructed.
     * Also deletes the storage if storage is marked as temporary.
     * @see self::getDestructed
     */
    public function __destruct()
    {
        
        
        $this->close();
        
        if ($this->getIsTemporary()) {
            unlink($this->filePath);
            
            if (!is_null($this->getRealFilePath()) && file_exists((string)$this->getRealFilePath())) {
                unlink((string)$this->getRealFilePath());
            }
            
            if (!is_null($this->getSymLinkFilePath()) && file_exists((string)$this->getSymLinkFilePath())) {
                unlink((string)$this->getSymLinkFilePath());
            }
        }
    }
}
