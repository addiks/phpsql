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

namespace Addiks\PHPSQL\Entity\Page;

/**
 * A fixed size data-block representing a data-row.
 *
 * TODO: does this even get used somewhere?
 *       Maybe it can be used in the future when column-data's are not is seperate files.
 */
class RowPage
{
    
    public function __construct(TableSchema $schema)
    {
        $this->tableSchema = $schema;
    }
    
    private $tableSchema;
    
    /**
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return $this->tableSchema;
    }
    
    private $data;
    
    public function setData($pageData)
    {
        if (strlen($pageData)!==$this->getTableSchema()->getRowPageSize()) {
            throw new ErrorException("Given pagedata to row with wrong length! (actual ".strlen($pageData).", expected {$this->getTableSchema()->getRowPageSize()})");
        }
        $this->data = $pageData;
    }
    
    public function getData()
    {
        if (is_null($this->data)) {
            $this->setData(str_pad("", $this->getTableSchema()->getRowPageSize(), "\0"));
        }
        return $this->data;
    }
    
    public function getCellData($columnId)
    {
        $position = $this->getTableSchema()->getCellPositionInPage($columnId);
        $length   = $this->getTableSchema()->getColumn($columnId)->getCellSize();
        
        $data = substr($this->getData(), $position, $length);
        
        if (!$this->getTableSchema()->getColumn($columnId)->isBinary()) {
            $data = rtrim($data, "\0");
        }
        
        return $data;
    }
    
    public function setCellData($columnId, $columnData)
    {
        $position = $this->getTableSchema()->getCellPositionInPage($columnId);
        $length   = $this->getTableSchema()->getColumn($columnId)->getCellSize();
        
        if (strlen($columnData)>$length) {
            $columnData = substr($columnData, 0, $length);
        }
        
        $columnData = str_pad($columnData, $length, "\0", STR_PAD_RIGHT);
        
        $data = $this->getData();
        $data = substr($data, 0, $position) .
                $columnData .
                substr($data, ($position+$length));
        
        $this->setData($data);
    }
    
    public function getDataArray()
    {
        
        $dataArray = array();
        
        foreach ($this->getTableSchema()->listColumns() as $columnId => $columnPage) {
            /* @var $columnPage Column */
            
            $dataArray[$columnPage->getName()] = $this->getCellData($columnId);
        }
        
        return $dataArray;
    }
}
