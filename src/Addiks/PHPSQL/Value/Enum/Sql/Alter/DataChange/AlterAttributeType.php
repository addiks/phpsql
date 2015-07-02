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

namespace Addiks\PHPSQL\Value\Enum\Sql\Alter\DataChange;

use Addiks\PHPSQL\Value\Enum;

class AlterAttributeType extends Enum
{
    
    ### CHANGE COLUMN-SET
    
    const ADD = "ADD";
    const MODIFY = "MODIFY";
    const DROP = "DROP";
    
    ### TABLE METADATA
    
    const RENAME = "RENAME";
    const CONVERT  = "CONVERT";
    
    ### ORDER BY
    
    const ORDER_BY_DESC = "ORDER BY DESC";
    const ORDER_BY_ASC = "ORDER BY ASC";
    
    ### COLUMN ORDER
    
    const DEFAULT_VALUE = "DEFAULT";
    const SET_FIRST = "FIRST";
    const SET_AFTER = "AFTER";
    
    ### CHARACTER SET
    
    const CHARACTER_SET = "CHARACTER SET";
    const COLLATE = "COLLATE";
}
