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

namespace Addiks\PHPSQL\Entity\Job\Part;

use Addiks\PHPSQL\Entity\Job\Part\Join\Table;

use Addiks\PHPSQL\Entity\Job\Part;

class Join extends Part
{

    private $rightTables = array();
    
    private $leftTables = array();
    
    public function addTable(Table $table)
    {
        
        if ($table->getIsRight()) {
            $this->rightTables[] = $table;
        } else {
            $this->leftTables[] = $table;
        }
    }
    
    public function getTables()
    {
        
        $tables = array();
        
        foreach ($this->rightTables as $table) {
            /* @var $table Table */
            
            array_unshift($tables, $table);
        }
        
        foreach ($this->leftTables as $table) {
            /* @var $table Table */
                
            array_push($tables, $table);
        }
        
        return $tables;
    }
}
