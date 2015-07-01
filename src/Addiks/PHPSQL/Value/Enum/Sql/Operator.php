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

namespace Addiks\Database\Value\Enum\Sql;

use Addiks\Common\Value\Enum;

class Operator extends Enum{
	
	const OP_EQUAL = "% = %";
	const OP_EQUAL_NULLSAFE = "% <=> %";
	
	const OP_NOT_EQUAL = "% != %";
	const OP_LESSERGREATER = "% <> %";
	
	const OP_LESSEREQUAL = "% <= %";
	const OP_LESSER = "% < %";
	
	const OP_GREATEREQUAL = "% >= %";
	const OP_GREATER = "% > %";
	
	const OP_IS = "% IS %";
	const OP_IS_NOT = "% IS NOT %";
	const OP_IS_NULL = "% IS NULL";
	const OP_IS_NOT_NULL = "% IS NOT NULL";
	const OP_BETWEEN = "% BETWEEN % AND %";
	const OP_NOT_BETWEEN = "% NOT BETWEEN % AND %";
	
	const OP_AND = "% AND %";
	const OP_OR = "% OR %";
	
	const OP_ADDITION       = "% + %";
	const OP_SUBTRACTION    = "% - %";
	const OP_MULTIPLICATION = "% * %";
	const OP_DIVISION       = "% / %";
}