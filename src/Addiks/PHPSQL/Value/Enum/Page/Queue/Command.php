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

namespace Addiks\Database\Value\Enum\Page\Queue;

use Addiks\Common\Value\Enum;

class Command extends Enum{
	
	const SELECT   = 0x01;
	const INSERT   = 0x02;
	const UPDATE   = 0x03;
	const DELETE   = 0x04;
	const TRUNCATE = 0x05;
	
	const SHOW     = 0x06;
	const CREATE   = 0x07;
	const ALTER    = 0x08;
	const DROP     = 0x09;
	
	const UNION    = 0x0A;
}