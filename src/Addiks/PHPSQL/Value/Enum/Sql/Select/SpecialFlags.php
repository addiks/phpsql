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

namespace Addiks\PHPSQL\Value\Enum\Sql\Select;

use Addiks\PHPSQL\Value\Enum;

class SpecialFlags extends Enum
{
    
    const FLAG_ALL                 = 0x001; # 0000 0000 0001
    const FLAG_DISTINCT            = 0x002; # 0000 0000 0010
    const FLAG_DISTINCTROW         = 0x004; # 0000 0000 0100
    const FLAG_HIGH_PRIORITY       = 0x008; # 0000 0000 1000
    const FLAG_STRAIGHT_JOIN       = 0x010; # 0000 0001 0000
    const FLAG_SQL_SMALL_RESULT    = 0x020; # 0000 0010 0000
    const FLAG_SQL_BIG_RESULT      = 0x040; # 0000 0100 0000
    const FLAG_SQL_BUFFER_RESULT   = 0x080; # 0000 1000 0000
    const FLAG_SQL_CACHE           = 0x100; # 0001 0000 0000
    const FLAG_SQL_NO_CACHE        = 0x200; # 0010 0000 0000
    const FLAG_SQL_CALC_FOUND_ROWS = 0x400; # 0100 0000 0000
}
