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

/**
 * Resource for managing caches.
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 * @subpackage Common
 */
class Caches extends Storages
{
    
    /**
     * Constructor.
     * @see self::checkCleanupCronjob
     * @param Context $context
     */
    public function __construct()
    {
        
#		$this->checkCleanupCronjob();
    }
    
    /**
     * makes sure that the cronjob still exists which cleans up old caches.
     * @see Cronjobs
     */
    protected function checkCleanupCronjob()
    {
    
        /* @var $cronjobs Cronjobs */
        $this->factorize($cronjobs);
        
        /* @var $route Route */
        $this->factorize($route, ["Cache", "CLEAN"]);
        
        $matchCronjob = new Cronjob();
        $matchCronjob->fromRoute($route);
        
        ### SEARCH CRONJOB ###
        
        $matchCommand = $matchCronjob->getCommand();
        
        foreach ($cronjobs->getCronjobs() as $cronjob) {
            if ($cronjob->getCommand() === $matchCommand) {
                return;
            }
        }
        
        ### INSTALL CRONJOB ###
        
        $matchCronjob->setHour(3);
        $matchCronjob->setMinute(0);
        
        $cronjobs->addCronjob($matchCronjob);
    }
    
    const PATH_INDEX  = "%s/Caches/Index/%s";
    const PATH_DATA   = "%s/Caches/Data/%s/%s/%s";
    const PATH_MIRROR = "%s/Caches/MirrorIndex/%s/%s";
    
    protected function isCacheDeactivated($identifier)
    {
        
        $namespace = $this->getCallerNamespacePart();
        
        $indexPath = $this->getIndexPath($namespace, $identifier);
        $dataPath  = $this->getStorageDataPath($namespace, $identifier);
        
        /* @var $dataDirectory \Addiks\Common\Value\Text\Directory\Data */
        $this->factorize($dataDirectory);
        
        $indexPath = substr($indexPath, strlen((string)$dataDirectory));
        $dataPath  = substr($dataPath, strlen((string)$dataDirectory));
        
        $cacheDeactivated = false;
        
        foreach ([$indexPath, $dataPath] as $checkPath) {
            $checkPathArray = explode("/", $checkPath);
                
            do {
                $checkPath = implode("/", $checkPathArray);
                $checkPath = "{$dataDirectory}/{$checkPath}.cachelock";
        
                if (file_exists($checkPath)) {
                    return true;
                }
            } while (is_string(array_pop($checkPathArray)));
        }
        
        return false;
    }
    
    /**
     * Acquires a cache with given identifier.
     *
     * @param string $identifier
     * @see Storages::acquireStorage
     * @return Storage
     */
    public function acquireCache($identifier)
    {
        
        static $deactivatedCacheIds = array();
        
        /* @var $storage Storage */
        $storage = parent::acquireStorage($identifier);
        
        if ($this->isCacheDeactivated($identifier) && !isset($deactivatedCacheIds[$identifier])) {
            $deactivatedCacheIds[$identifier] = $identifier;
            $storage->clear();
            $storage->flush();
        }
        
        return $storage;
    }
    
    private $usedCacheBackend = false;
    
    /**
     * Checks different caching-backend's and returnes the one that should be usable.
     *
     * @return CacheBackendInterface|null
     */
    public function getUsableCacheBackend()
    {
        
        if ($this->usedCacheBackend === false) {
            /* @var $memcacheBackend MemcacheClient */
            $this->factorize($memcacheBackend);
            
            $cacheBackend = null;
            
            switch(true){
                case $memcacheBackend->hasServers();
                    $cacheBackend = $memcacheBackend;
                    break;
            }
            
            $this->usedCacheBackend = $cacheBackend;
        }
        
        return $this->usedCacheBackend;
    }
}
