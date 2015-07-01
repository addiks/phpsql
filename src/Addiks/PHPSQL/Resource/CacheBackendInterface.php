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

interface CacheBackendInterface
{
    
    public function set($key, $value);
    
    public function get($key);
    
    public function remove($key);
    
    public function add($key, $value);
    
    public function replace($key, $value);
    
    public function flush($key);
}
