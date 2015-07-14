<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Value\Database;

use Addiks\PHPSQL\Value\Text\Line;
use Addiks\PHPSQL\Value\Database\Dsn\InmemoryDsn;
use Addiks\PHPSQL\Value\Database\Dsn\InternalDsn;

/**
 * The "Data Source Name".
 * Holds an address-like data-block for connecting to a database.
 * Currently only mysql is supported.
 */
abstract class Dsn extends Line
{

    /**
     * gets the adapter-name for Zend.
     * @see Adapter
     * @return string
     */
    public function getZendAdapterName()
    {
        switch($this->getDriverName()){
            default:
            case 'mysql':
                return 'PDO_MYSQL';
                break;
        }
    }
    
    /**
     * gets the driver-name.
     * @return string
     */
    public function getDriverName()
    {
        $value = $this->getValue();
        
        $value = explode(":", $value);
        
        $driver = reset($value);
        
        return $driver;
    }

    public function factorizeDSN($dsn)
    {
        $dsnValue = null;

        if (substr($dsn, 0, 9) === "inmemory:") {
            $dsnValue = InmemoryDsn::factory($dsn);

        } elseif (substr($dsn, 0, 9) === "internal:") {
            $dsnValue = InternalDsn::factory($dsn);

        } else {
            throw new ErrorException("Internal PDO cannot handle this DSN: {$dsn}");
        }

        return $dsnValue;
    }
}
