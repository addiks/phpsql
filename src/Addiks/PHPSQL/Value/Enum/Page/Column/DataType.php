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

namespace Addiks\PHPSQL\Value\Enum\Page\Column;

use Addiks\PHPSQL\Value\Enum;

class DataType extends Enum
{
    
    ### NUMBERS
    
    /* @Addiks\Datatype(bytelength=1) */
    const BIT       = 0x01;

    /* @Addiks\Datatype(bytelength=1) */
    const BOOL      = 0x02;
    
    /* @Addiks\Datatype(bytelength=1) */
    const BOOLEAN   = 0x02;
    
    /* @Addiks\Datatype(bytelength=1) */
    const TINYINT   = 0x03;
    
    /* @Addiks\Datatype(bytelength=2) */
    const SMALLINT  = 0x04;
    
    /* @Addiks\Datatype(bytelength=3) */
    const MEDIUMINT = 0x05;
    
    /* @Addiks\Datatype(bytelength=4) */
    const INT       = 0x06;
    
    /* @Addiks\Datatype(bytelength=4) */
    const INTEGER   = 0x06;
    
    /* @Addiks\Datatype(bytelength=8) */
    const BIGINT    = 0x07;
    
    /* @Addiks\Datatype(bytelength=4) */
    const DEC       = 0x08;
    
    /* @Addiks\Datatype(bytelength=4) */
    const DECIMAL   = 0x08;
    
    /* @Addiks\Datatype(bytelength=4, secondbytelength=4) */
    const FLOAT            = 0x09;
    
    /* @Addiks\Datatype(bytelength=8, secondbytelength=4) */
    const DOUBLE           = 0x0A;
    
    /* @Addiks\Datatype(bytelength=8) */
    const DOUBLE_PRECISION = 0x0A;
    
    ### DATE / TIME
    
    /* '0000-00-00'
	 * @Addiks\Datatype(bytelength=10) */
    const DATE      = 0x0B;
    
    /* '0000-00-00 00:00:00'
	 * @Addiks\Datatype(bytelength=19) */
    const DATETIME  = 0x0C;
    
    /* '0000-00-00 00:00:00'
	 * @Addiks\Datatype(bytelength=19) */
    const TIMESTAMP = 0x0D;
    
    /* '00:00:00'
	 * @Addiks\Datatype(bytelength=8) */
    const TIME      = 0x0E;
    
    /* '00:00:00'
	 * @Addiks\Datatype(bytelength=4) */
    const YEAR      = 0x0F;
    
    ### STRING
    
    /* @Addiks\Datatype(bytelength=1, defaultLength=1) */
    const CHAR       = 0x10;
    
    /* @Addiks\Datatype(bytelength=64) */
    const VARCHAR    = 0x11;
    
    /* @Addiks\Datatype(bytelength=64) */
    const BINARY     = 0x10;
    
    /* @Addiks\Datatype(bytelength=64) */
    const VARBINARY  = 0x11;
    
    
    /* @Addiks\Datatype(bytelength=255, binary=true) */
    const TINYBLOB   = 0x12;
    
    /* @Addiks\Datatype(bytelength=255) */
    const TINYTEXT   = 0x15;
    
    /* @Addiks\Datatype(bytelength=16777216, binary=true) */
    const CLOB       = 0x13;
    
    /* @Addiks\Datatype(bytelength=16777216, binary=true) */
    const BLOB       = 0x13;
    
    /* @Addiks\Datatype(bytelength=16777216) */
    const TEXT       = 0x17;
    
    /* @Addiks\Datatype(bytelength=4294967296, binary=true) */
    const MEDIUMBLOB = 0x13;
    
    /* @Addiks\Datatype(bytelength=4294967296) */
    const MEDIUMTEXT = 0x16;
    
    /* @Addiks\Datatype(bytelength=4294967296, binary=true) */
    const LONGBLOB   = 0x14;
    
    /* @Addiks\Datatype(type="storage" */
    const LONGTEXT   = 0x18;
    
    
    /* @Addiks\Datatype(bytelength=4) */
    const ENUM = 0x19;

    /* @Addiks\Datatype(bytelength=4) */
    const SET  = 0x19;
}
