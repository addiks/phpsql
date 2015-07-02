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

namespace Addiks\PHPSQL\Resource;

use ErrorException;
use Addiks\PHPSQL\Value\Text\Directory\Data;
use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Tool\CustomIterator;
use Addiks\PHPSQL\Value\Text\Filepath;

/**
 * This resource is for accassing storages.
 * @see Storage
 *
 */
class Storages
{
    
    const HASH_LENGTH = 6;
    
    /**
     * Acquires a storage-entity for a given storage-path.
     * Recognises who has called this and prepends that to the path.
     *
     * @see Storage
     *
     * @param string $path
     */
    public function acquireStorage($path, $namespace = null)
    {
        
        /* @var $dataDirectory Data */
        $dataDirectory = $this->factory("\Addiks\PHPSQL\Value\Text\Directory\Data");
        
        $path = $this->cleanPath($path);
        
        if (is_null($namespace)) {
            $namespace = $this->getCallerNamespacePart();
        }
        
        $indexPath = $this->getIndexPath($namespace, $path);
        
        if (!isset($this->storages[$indexPath])) {
            $mirrorPath = $this->getMirrorIndexPath($namespace, $path);
            $doMirror = false;
            
            if (!is_link($indexPath)) {
                $filePath = $this->getStorageDataPath($namespace, $path);
                    
                if (!is_dir(dirname($indexPath))) {
                    mkdir(dirname($indexPath), 0770, true);
                }
                
                if (!file_exists($filePath)) {
                    if (!is_dir(dirname($filePath))) {
                        mkdir(dirname($filePath), 0770, true);
                    }
                    touch($filePath);
                }
                
                symlink($filePath, $indexPath);
                
                ### MIRRORS ###
                
                foreach ([$this->getIndexPath($namespace, $path),
                         $this->getStorageDataPath($namespace, $path)] as $checkPath) {
                    $checkPath = substr($checkPath, strlen((string)$dataDirectory));
                    $checkPathArray = explode("/", $checkPath);
                    
                    do {
                        $checkPath = implode("/", $checkPathArray).".mirror";
                        
                        if (file_exists($checkPath)) {
                            $doMirror = true;
                            break 2;
                        }
                        
                    } while (is_string(array_pop($checkPathArray)));
                }
                
            } else {
                $filePath = realpath($indexPath);
            }
            
            $storage = new Storage(Filepath::factory($indexPath));
            $storage->setSymLinkFilePath(Filepath::factory($indexPath));
            $storage->setRealFilePath(Filepath::factory($filePath));
            
            if ($doMirror) {
                $mirrorStorage = new Storage(Filepath::factory($mirrorPath));
                    
                $storage->addDataMirror($mirrorStorage);
            }
            
            $this->storages[$indexPath] = $storage;
        }
        
        return $this->storages[$indexPath];
    }
    
    public function storageExists($path)
    {
        
        $path = $this->cleanPath($path);
        $namespace = $this->getCallerNamespacePart();
        
        $indexPath = $this->getIndexPath($namespace, $path);
        
        return file_exists($indexPath);
    }
    
    public function removeStorage($path)
    {
        
        $path = $this->cleanPath($path);
        $namespace = $this->getCallerNamespacePart();
        
        $indexPath = $this->getIndexPath($namespace, $path);
        
        if (!file_exists($indexPath)) {
            return;
        }
        
        $recursiveFileRemove = function ($path) use (&$recursiveFileRemove) {
            
            if (is_dir($path)) {
                foreach (new \DirectoryIterator($path) as $item) {
                    /* @var $item \DirectoryIterator */
                    
                    if ($item->isDot()) {
                        continue;
                    }
                    
                    $recursiveFileRemove($item->getRealPath());
                }
            } else {
                unlink($path);
            }
            
        };
        
        if (is_dir($indexPath)) {
            $recursiveFileRemove($indexPath);
        }
        
        if (is_dir($indexPath)) {
            Real::rrmdir($indexPath);
                
        } elseif (is_file($indexPath) || is_link($indexPath)) {
            unlink($indexPath);
        }
    }
    
    /**
     * Gets an iterator with that you can iterate over a storage-path and
     * get all storages or sub-store-iterators in that path.
     *
     * @param string $path
     * @return CustomIterator
     */
    public function getStoreIterator($path, $recursive = true)
    {
        
        /* @var $dataDirectory \Addiks\PHPSQL\Value\Text\Directory\Data */
        $this->factorize($dataDirectory);
        
        $path = $this->cleanPath($path);
        $namespace = $this->getCallerNamespacePart();
        
        $folderPath = $this->getIndexPath($namespace, $path);
        
        if (!is_dir($folderPath)) {
            return new \ArrayIterator(array());
        }
        
        $directoryIterator = new \DirectoryIterator($folderPath);
        
        $namespace = $this->getCallerNamespacePart();
        
        $iterator = $this->getStorageIteratorFromDirectoryIterator($directoryIterator, $recursive, $namespace);
        
        return $iterator;
    }
    
    /**
     * Gets a storage-iterator from directory-iterator in index-path.
     * Please use self::getStoreIterator to get store-iterator for specific path.
     *
     * @param \DirectoryIterator $directoryIterator
     * @return CustomIterator
     */
    protected function getStorageIteratorFromDirectoryIterator(\DirectoryIterator $directoryIterator, $recursive = true, $namespace = "-")
    {
        
        /* @var $dataDirectory \Addiks\PHPSQL\Value\Text\Directory\Data */
        $this->factorize($dataDirectory);
        
        $storageResource = $this;
        
        /**
         * Iterates over  DirectoryIterator $directoryIterator.
         * Returnes directories as new custom-iterators of these kind and files as storages.
         * If not $recursive, skippes all directories.
         *
         * @var CustomIterator
         */
        $storageIterator = new CustomIterator(clone $directoryIterator, [
            'rewind' => function () use ($directoryIterator, &$recursive) {
                $directoryIterator->rewind();
                while ($directoryIterator->valid() && $directoryIterator->current()->isDot()) {
                    $directoryIterator->next();
                }
                while ($directoryIterator->valid() && !$recursive && $directoryIterator->current()->isDir()) {
                    $directoryIterator->next();
                }
            },
            'valid' => function () use ($directoryIterator, &$recursive) {
                return $directoryIterator->valid();
            },
            'next' => function () use ($directoryIterator, &$recursive) {
                $directoryIterator->next();
                while ($directoryIterator->valid() && $directoryIterator->current()->isDot()) {
                    $directoryIterator->next();
                }
                while ($directoryIterator->valid() && !$recursive && $directoryIterator->current()->isDir()) {
                    $directoryIterator->next();
                }
            },
            'key' => function () use ($directoryIterator, $storageResource, $dataDirectory, $namespace, &$recursive) {
            
                /* @var $item \DirectoryIterator */
                $item = $directoryIterator->current();
            
                return $item->getFilename();
            },
            'current' => function () use ($directoryIterator, $storageResource, $dataDirectory, $namespace, &$recursive) {
                
                /* @var $item \DirectoryIterator */
                $item = $directoryIterator->current();
                
                if ($item->isDir()) {
                    return $storageResource->getStorageIteratorFromDirectoryIterator($item, $recursive, $namespace);
                }
                
                $path = substr($item->getPathname(), strlen($this->getIndexPath($namespace)));
                
                return $storageResource->acquireStorage($path);
            }
        ]);
        
        return $storageIterator;
    }
    
    public function getFolderStoragePath($path)
    {
        
        /* @var $dataDirectory Data */
        $this->factorize($dataDirectory);
        
        $path = $this->cleanPath($path);
        $namespace = $this->getCallerNamespacePart();
        
        $indexPath = $this->getIndexPath($namespace, $path);
        $dataPath  = $this->getStorageDataPath($namespace, $path);
        
        if (strlen($path)<=0) {
            $originalPath = func_get_arg(0);
            throw new ErrorException("Invalid folder-storage-path '{$originalPath}' given!");
        }
        
        if (!file_exists($dataPath)) {
            mkdir($dataPath, 0775, true);
        }
        
        if (!file_exists($indexPath)) {
            if (!is_dir(dirname($indexPath))) {
                mkdir(dirname($indexPath), 0775, true);
            }
            
            symlink($dataPath, $indexPath);
        }
        
        return $indexPath;
    }
    
    ### PATH GETTER ###
    
    const PATH_INDEX  = "%s/Storages/Index/%s";
    const PATH_DATA   = "%s/Storages/Data/%s/%s/%s";
    const PATH_MIRROR = "%s/Storages/MirrorIndex/%s/%s";
    
    protected function getIndexPath($namespace, $path = null)
    {
    
        /* @var $dataDirectory \Addiks\PHPSQL\Value\Text\Directory\Data */
        $dataDirectory = $this->factory("\Addiks\PHPSQL\Value\Text\Directory\Data");
    
        if (!is_null($path)) {
            $path = "/{$path}";
        }
    
        return sprintf(static::PATH_INDEX, $dataDirectory, $namespace) . $path;
    }
    
    protected function getMirrorIndexPath($namespace, $path)
    {
        
        /* @var $dataDirectory \Addiks\PHPSQL\Value\Text\Directory\Data */
        $dataDirectory = $this->factory("\Addiks\PHPSQL\Value\Text\Directory\Data");
        
        return sprintf(static::PATH_MIRROR, $dataDirectory, $namespace, $path);
    }
    
    protected function getStorageDataPath($namespace, $path)
    {
    
        /* @var $dataDirectory \Addiks\PHPSQL\Value\Text\Directory\Data */
        $dataDirectory = $this->factory("\Addiks\PHPSQL\Value\Text\Directory\Data");
        
        $hash = substr(md5($path), 0, self::HASH_LENGTH);
        $hashArray = str_split($hash);
        $hashPart = implode("/", $hashArray);
        
        return sprintf(static::PATH_DATA, $dataDirectory, $hashPart, $namespace, $path);
    }
    
    ### HELPER ###
    
    /**
     * Cleans a given string to usable storage-path.
     *
     * @param string $path
     * @return string
     */
    private function cleanPath($path)
    {
        
        $path = trim($path);
        while ($path[0]==="/") {
            $path = substr($path, 1);
        }
        
        $path = preg_replace("/[^a-zA-Z0-9=#'+*.:,;\/_-]/is", "_", $path);
        
        return $path;
    }
    
    /**
     * Gets the namespace to use for storage-seperation by caller.
     *
     * @return string
     */
    public function getCallerNamespacePart()
    {
        
        $levels = 1;
        do {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $levels+2)[$levels+1];
            $levels++;
        } while (isset($trace['class']) && is_subclass_of($trace['class'], __CLASS__));
        
        $namespace = "-";
        
        if (isset($trace['class'])) {
            $classId = $trace['class'];
            while ($classId[0]==="\\") {
                $classId = substr($classId, 1);
            }
        
            $namespaceParts = explode("\\", $classId);
            
            if (count($namespaceParts)>=2) {
                $namespace = implode("-", [$namespaceParts[0], $namespaceParts[1]]);
            }
        }
        
        return $namespace;
    }
}
