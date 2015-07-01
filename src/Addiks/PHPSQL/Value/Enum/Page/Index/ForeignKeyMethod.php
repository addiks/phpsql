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

namespace Addiks\Database\Value\Enum\Page\Index;

use Addiks\Common\Value\Enum;

class ForeignKeyMethod extends Enum{
	
	const NO_ACTION = 0x00;
	const SET_NULL  = 0x01;
	const CASCADE   = 0x02;
	const RESTRICT  = 0x03;
}