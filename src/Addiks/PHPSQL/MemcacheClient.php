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

namespace Addiks\PHPSQL;

/**
 * This resource can connect to (multiple) memcache-servers.
 *
 * It can also scan the internal network for memcache-servers,
 * if they use the default port.
 *
 */
class MemcacheClient implements CacheBackendInterface
{
    
    /**
     * Constructor.
     * Automaticaly runs auto-connect if not specified else in parameters.
     * @param bool $autoConnect
     */
    public function __construct($autoConnect = true)
    {
        if ($autoConnect) {
            $this->autoConnect();
        }
    }
    
    ### SERVERS MANAGEMENT
    
    /**
     * The default port of memcache-servers.
     * @var int
     */
    const MEMCACHEDB_DEFAULT_PORT = 11211;
    
    /**
     * This method is used for automatic connection.
     * It will be automatically called in constructor,
     * if not otherwise specified in constructor parameters.
     *
     * This method will connect to all known memcache-servers.
     *
     * If no servers are known, it will scan the internal network(s),
     * and store the result int the internal storage system.
     */
    public function autoConnect()
    {
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        /* @var $addressesStorage Storage */
        $addressesStorage = $storages->acquireStorage("Memcache/LocalServers");
        
        if ($addressesStorage->getLength() <= 0) {
            $addresses = $this->scanInternalNetwork();
            
            if (count($addresses)>0) {
                 $addressesStorage->setData(implode(",", $addresses));
            } else {
                 $addressesStorage->setData('NONE');
            }
            
        } else {
            $addresses = explode(",", $addressesStorage->getData());
            
            if ($addresses === array('NONE')) {
                $addresses = array();
            }
        }
        
        foreach ($addresses as $address) {
            list($ip, $port) = explode(":", $address);
            
            $this->connect($ip, $port);
        }
    }

    /**
     * Checks the local nearest network for memcachedb server(s).
     * Will check if the default memcachedb-port is open on every IP scanned.
     *
     * Searches for IP's in the system.
     * Iterates through the last octet of every found IP.
     *
     * @return array
     */
    public function scanInternalNetwork()
    {
        
        $result = array();
        
        foreach ($this->getIpPrefixesByProcNet() as $ipPrefix) {
            for ($lastOctet = 1; $lastOctet <= 255; $lastOctet++) {
                $address = "{$ipPrefix}.{$lastOctet}";
                
                $success = $this->checkAdress($address, self::MEMCACHEDB_DEFAULT_PORT);
                
                if ($success) {
                    $result[] = "{$address}:".self::MEMCACHEDB_DEFAULT_PORT;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * This fetches usable IP-prefixes from /proc/net folder.
     * These prefixes will be used to scan local network.
     * @see self::scanInternalNetwork
     */
    protected function getIpPrefixesByProcNet()
    {
        
        $ipPrefixes = array();
        
        /**
         * A list of file-paths where usable IP's can be found.
         * @var array
         */
        $sourceFiles = ['/proc/net/fib_trie', '/proc/net/arp'];
        
        foreach ($sourceFiles as $sourceFile) {
            if (!file_exists($sourceFile)) {
                continue;
            }
            
            $data = file_get_contents($sourceFile);
            
            preg_match_all(
                "/(\d{1,3}\.\d{1,3}\.\d{1,3})\.\d{1,3}/is",
                $data,
                $matches,
                PREG_SET_ORDER
            );
            
            foreach ($matches as $match) {
                if ($match[1][0] === '0') {
                    continue;
                }
                
                $ipPrefixes[] = $match[1];
            }
        }
        
        $ipPrefixes = array_unique($ipPrefixes);
        
        return $ipPrefixes;
    }
    
    private $servers = array();
    
    /**
     * Checks if there are servers connected to this client.
     * If not, the memcache-client cannot be used.
     *
     * @return boolean
     */
    public function hasServers()
    {
        return count($this->servers) > 0;
    }
    
    /**
     * Gets the internal server array by key.
     *
     * @param string $key
     */
    protected function getServerByKey($key)
    {
        
        $length = log(16, count($this->servers));
        
        $hash = substr(md5($key), 0, $length);
        $hashDec = hexdec($hash);
        
        return $this->servers[$hashDec % $length];
    }
    
    /**
     * Gets the internal server array by address/port or by key.
     *
     * @param string $address
     * @param string $port
     *
     * @param string $key
     */
    protected function getServerByAddress($address = null, $port = null, $key = null)
    {
        if (is_null($port)) {
            $port = self::MEMCACHEDB_DEFAULT_PORT;
        }
        if (is_null($address) && !is_null($key)) {
            return $this->getServerByKey($key);
        }
        foreach ($this->servers as $server) {
            if ($server['address'] === $address && $server['port'] === $port) {
                return $server;
            }
        }
        $this->connect($address, $port);
        return $this->getServerByAddress($address, $port);
    }
    
    /**
     * Checks, if a given ip/port address can be used as memcache-server.
     *
     * @param string $address
     * @param string $port
     */
    public function checkAdress($address, $port)
    {
        
        $success = false;
        
    #   $socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname("tcp"));
        
    #	socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 0, 'usec' => 5000]);
        
        $connection = @fsockopen($address, $port, $errno, $errstr, 0.025);
        
        if (is_resource($connection)) {
            fwrite($socket, "stats_r\n");
            
            $data = fread($socket, 4096);
            
            if (strlen($data)>0) {
                $matchCount = preg_match_all("/STAT\s+([a-z_]+)\s+([^_r\n]+)/is", $data, $matches, PREG_SET_ORDER);
                
                if ($matchCount > 0) {
                    foreach ($matches as $match) {
                        list($string, $key, $value) = $match;
                            
                        $stats[$key] = $value;
                    }
                    
                    $success = true;
                    
                    // check if some stats exist
                    foreach (['pid', 'time', 'version', 'bytes'] as $expectedKey) {
                        if (!isset($stats[$expectedKey])) {
                            $success = false;
                        }
                    }
                }
            }
            
            fclose($socket);
        }
        
        return $success;
    }
    
    ### MANAGE SINGLE SERVER
    
    /**
     *
     * @return string
     *
     * @param string $address
     * @param string $port
     */
    public function connect($address, $port)
    {
        
        $socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname("tcp"));
        
        $success = socket_connect($socket, $address, $port);
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 3, 'usec' => 0]);
        socket_set_nonblock($socket);
        
        if (!$success) {
            throw new Exception("Could not connect to '{$address}:{$port}'!");
        }
        
        $this->servers[] = [
            'address' => $address,
            'port'    => $port,
            'socket'  => $socket,
        ];
    }
    
    /**
     * Disconnects from (a) server.
     *
     * @param string $address
     * @param string $port
     */
    public function disconnect($address, $port)
    {
        
        foreach ($this->servers as $key => $server) {
            if ($server['address'] === $address && $server['port'] === $port) {
                socket_close($server['socket']);
                unset($this->servers[$key]);
                usort($this->servers, function ($serverA, $serverB) {
                    return $serverA['address'] > $serverB['address'];
                });
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gets stats from server.
     *
     * @param string $address
     * @param string $port
     */
    public function getServerParameters($address, $port)
    {
        $server = $this->getServerByAddress($address, $port);
        $socket = $server['socket'];
        
        $length = strlen($value);
        
        socket_write($socket, "stats_r\n");
        
        $data = socket_read($socket, 4096);
        preg_match_all("/STAT\s+([a-z_]+)\s+([^_r\n]+)/is", $data, $matches, PREG_SET_ORDER);
        
        $stats = array();
        foreach ($matches as $match) {
            list($string, $key, $value) = $match;
                
            $stats[$key] = $value;
        }
        
        return $stats;
    }
    
    ### VALUES
    
    /**
     * Gets a value from the server
     *
     * @param string $key
     * @param string $flags
     *
     * @param string $address
     * @param string $port
     */
    public function get($key, $address = null, $port = null, &$flags = "")
    {
        
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
        
        socket_write($socket, "get {$key}_r\n");
        
        // if its not "VALUE", it will be probably "r\n"
        if (socket_read($socket, 5) !== "VALUE") {
            return null;
        }
        
        if (socket_read($socket, 2+strlen($key))!==" {$key} ") {
            return null;
        }
        
        $flags = "";
        while (is_numeric($char = socket_read($socket, 1))) {
            $flags .= $char;
        }
        
        $length = "";
        while (is_numeric($char = socket_read($socket, 1))) {
            $length .= $char;
        }
        
        socket_read($socket, 1); // read "_r\n"
        
        $data = socket_read($socket, (int)$length);
        
        return $data;
    }
    
    /**
     * Sets a value on the server.
     * If a value is present, overwrites anything that is present.
     *
     * @param string $key
     * @param string $value
     *
     * @param string $address
     * @param string $port
     */
    public function set($key, $value, $address = null, $port = null)
    {
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
    
        $length = strlen($value);
        
        socket_write($socket, "set {$key} 0 0 {$length}_r\n");
        socket_write($socket, "{$value}_r\n");
        
        $check = socket_read($socket, 7);
        
        if ($check === "r\n") {
            return true;
        }
        return false;
    }
    
    /**
     * Adds a value to a server if it does not exist yet.
     *
     * @param string $key
     * @param string $value
     *
     * @param string $address
     * @param string $port
     */
    public function add($key, $value, $address = null, $port = null)
    {
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
        
        $length = strlen($value);
        
        socket_write($socket, "add {$key} 0 0 {$length}_r\n");
        socket_write($socket, "{$value}_r\n");
        
        $check = socket_read($socket, 7);
        
        if ($check === "r\n") {
            return true;
        }
        return false;
    }
    
    /**
     * Acts like 'set' when value is present,
     * Does not do anything when value is not set yet.
     *
     * @param string $key
     * @param string $value
     *
     * @param string $address
     * @param string $port
     */
    public function replace($key, $value, $address = null, $port = null)
    {
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
        
        $length = strlen($value);
        
        socket_write($socket, "replace {$key} 0 0 {$length}_r\n");
        socket_write($socket, "{$value}_r\n");
        
        $check = socket_read($socket, 7);
        
        if ($check === "r\n") {
            return true;
        }
        return false;
    }
    
    /**
     * Removes an existing value from server.
     *
     * @param string $key
     *
     * @param string $address
     * @param string $port
     */
    public function remove($key, $address = null, $port = null)
    {
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
        
        socket_write($socket, "delete {$key}_r\n");
        
        
        $check = socket_read($socket, 8);
        
        if ($check === "r\n") {
            return true;
        }
        return false;
    }
    
    /**
     * Flushes all commands on the server
     *
     * @param string $address
     * @param string $port
     */
    public function flush($address = null, $port = null)
    {
        $server = $this->getServerByAddress($address, $port, $key);
        $socket = $server['socket'];
        
        socket_write($socket, "flush_all_r\n");
        
        $check = socket_read($socket, 3);
        
        if ($check === "r\n") {
            return true;
        }
        return false;
    }
}
