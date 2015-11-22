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

namespace Addiks\PHPSQL\Value\Enum\Page\Schema;

use Addiks\PHPSQL\Value\Enum;

class Engine extends Enum
{

    const VIEW        = 0x00;
    const MYISAM      = 0x01;
    const INNODB      = 0x02;
    const NDBCLUSTER  = 0x03;
    const MEMORY      = 0x04;
    const EXAMPLE     = 0x05;
    const FEDERATED   = 0x06;
    const ARCHIVE     = 0x07;
    const CSV         = 0x08;
    const BLACKHOLE   = 0x09;
    const INFINIDB    = 0x0A;
    const IBMDB2I     = 0x0B;
    const BRIGHTHOUSE = 0x0C;
    const KFDB        = 0x0D;
    const SCALEDB     = 0x0E;
    const TOKUDB      = 0x0F;
    const XTRADB      = 0x10;
    const SPIDER      = 0x11;
    const MRG_MYISAM  = 0x12;
    const MARIADB     = 0x13;
    const PBXT        = 0x14;
}
