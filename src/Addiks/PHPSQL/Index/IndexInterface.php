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

namespace Addiks\PHPSQL\Index;

interface IndexInterface
{
    
    public function getIndexSchema();

    public function updateRow(array $oldRow, array $newRow, $rowId);

    public function searchRow(array $row);
    
    public function insertRow(array $row, $rowId);

    public function removeRow(array $row, $rowId);

    public function update($rowId, $oldValue, $newValue);

    public function search($value);
    
    public function insert($value, $rowId);
    
    public function remove($value, $rowId);

    public function getIterator($beginValue = null, $endValue = null);

}
