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
    
    const BIT              = 0x01;
    const BOOL             = 0x02;
    const BOOLEAN          = 0x02;
    const TINYINT          = 0x03;
    const SMALLINT         = 0x04;
    const MEDIUMINT        = 0x05;
    const INT              = 0x06;
    const INTEGER          = 0x06;
    const BIGINT           = 0x07;
    const DEC              = 0x08;
    const DECIMAL          = 0x08;
    const FLOAT            = 0x09;
    const DOUBLE           = 0x0A;
    const DOUBLE_PRECISION = 0x0A;
    
    ### DATE / TIME

    const DATE      = 0x0B;
    const DATETIME  = 0x0C;
    const TIMESTAMP = 0x0D;
    const TIME      = 0x0E;
    const YEAR      = 0x0F;
    
    ### STRING

    const CHAR       = 0x10;
    const VARCHAR    = 0x11;
    const BINARY     = 0x10;
    const VARBINARY  = 0x11;
    const TINYBLOB   = 0x12;
    const TINYTEXT   = 0x15;
    const CLOB       = 0x13;
    const BLOB       = 0x13;
    const TEXT       = 0x17;
    const MEDIUMBLOB = 0x13;
    const MEDIUMTEXT = 0x16;
    const LONGBLOB   = 0x14;
    const LONGTEXT   = 0x18;

    const ENUM       = 0x19;
    const SET        = 0x19;

    public function isLikeEnum()
    {
        return in_array($this->getValue(), [
            self::ENUM,
        ]);
    }

    public function isLikeSet()
    {
        return in_array($this->getValue(), [
            self::SET,
        ]);
    }

    public function isInFile()
    {
        return in_array($this->getValue(), [
            self::LONGTEXT,
        ]);
    }

    public function getByteLength()
    {

        $map = array(
            self::BIT              => 1,
            self::BOOL             => 1,
            self::BOOLEAN          => 1,
            self::TINYINT          => 1,
            self::CHAR             => 1,
            self::SMALLINT         => 2,
            self::MEDIUMINT        => 3,
            self::YEAR             => 4,
            self::DEC              => 4,
            self::DECIMAL          => 4,
            self::FLOAT            => 4,
            self::INT              => 4,
            self::INTEGER          => 4,
            self::ENUM             => 4,
            self::SET              => 4,
            self::TIME             => 8,
            self::DOUBLE           => 8,
            self::DOUBLE_PRECISION => 8,
            self::BIGINT           => 8,
            self::DATE             => 10,
            self::TIMESTAMP        => 19,
            self::DATETIME         => 19,
            self::VARCHAR          => 64,
            self::BINARY           => 64,
            self::VARBINARY        => 64,
            self::TINYBLOB         => 255,
            self::TINYTEXT         => 255,
            self::CLOB             => 16777216,
            self::BLOB             => 16777216,
            self::TEXT             => 16777216,
            self::MEDIUMBLOB       => 4294967296,
            self::MEDIUMTEXT       => 4294967296,
            self::LONGBLOB         => 4294967296,
        );

        $flag = $this->getValue();
        $bytelength = null;
        if (isset($map[$flag])) {
            $bytelength = $map[$flag];
        }

        return $bytelength;
    }

    public function getSecondByteLength()
    {
        $bytelength = null;
        $flag = $this->getValue();

        $map = array(
            self::FLOAT  => 4,
            self::DOUBLE => 4,
        );

        if (isset($map[$flag])) {
            $bytelength = $map[$flag];
        }

        return $bytelength;
    }

    public function getDefaultLength()
    {
        $bytelength = null;
        $flag = $this->getValue();

        $map = array(
            self::CHAR => 1,
        );

        if (isset($map[$flag])) {
            $bytelength = $map[$flag];
        }

        return $bytelength;
    }

    public function isBinary()
    {
        return in_array($this->getValue(), [
            self::LONGBLOB,
            self::MEDIUMBLOB,
            self::BLOB,
            self::CLOB,
            self::TINYBLOB
        ]);
    }
}
