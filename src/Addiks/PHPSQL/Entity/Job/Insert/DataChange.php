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

namespace Addiks\PHPSQL\Entity\Job\Insert;

use Addiks\PHPSQL\Entity\Job;

class DataChange extends Job
{
    
    private $column;
    
    public function setColumn(Column $column)
    {
        $this->column = $column;
    }
    
    public function getColumn()
    {
        return $this->column;
    }
    
    private $value;
    
    public function setValue($value)
    {
        $this->value = $value;
    }
}
