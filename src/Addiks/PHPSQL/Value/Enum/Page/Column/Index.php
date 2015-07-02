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

class Index extends Enum
{
    
    const NOINDEX = 0x00;
    const PRIMARY = 0x01;
    const UNIQUE  = 0x02;
    const KEY     = 0x03;
}
