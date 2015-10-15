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

namespace Addiks\PHPSQL\Result;

use IteratorAggregate;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Result\ResultInterface;

/**
 *
 */
class ResultWriter implements IteratorAggregate
{
    
    public function __construct(ResultInterface $result)
    {
        
        $this->result = $result;
        
        $headers = $result->getHeaders();
        
        $columnWidths = array();
        
        foreach ($headers as $header) {
            $columnWidths[$header] = strlen($header);
        }
        
        foreach ($result as $rowId => $row) {
            foreach ($row as $key => $cell) {
                if (is_null($cell)) {
                    $cell = "NULL";
                }
                if ($columnWidths[$key] < strlen($cell)) {
                    $columnWidths[$key] = strlen($cell);
                }
            }
        }
        
        $this->columnWidths = $columnWidths;
    }
    
    private $result;
    
    public function getResult()
    {
        return $this->result;
    }
    
    private $columnWidths;
    
    public function getRowAsTableRow(array $row)
    {
        
        $line = " |";
        
        foreach ($row as $key => $cell) {
            if (is_null($cell)) {
                $cell = "NULL";
            }
            
            $flag = is_numeric($cell) ?STR_PAD_LEFT :STR_PAD_RIGHT;
            
            $cell = str_pad($cell, $this->columnWidths[$key], " ", $flag);
            
            $line .= " {$cell} |";
        }
        
        return $line . "\n";
    }
    
    public function getFillerLine()
    {
        
        $line = " +";
        
        foreach ($this->columnWidths as $key => $columnWidth) {
            $strokes = str_pad("", $this->columnWidths[$key], "-");
            
            $line .= "-{$strokes}-+";
        }
        
        return $line . "\n";
    }
    
    public function getIterator()
    {
        
        /**
         * Flags what special lines has been written and what not.
         *
         * @var array
         */
        $specialLineFlags = [
            'tableHead'  => false,
            'header'     => false,
            'headerFoot' => false,
            'tableFoot'  => false,
        ];
        
        $step = 1;
        
        /* @var $result Interface */
        $result = $this->getResult();
        
        $writer = $this;
        
        $iterator = $result;#->getIterator();
        
        return new CustomIterator($iterator, [
            'rewind' => function ($rewindClosure) use (&$step, $result) {
                if ($result->getHasResultRows()) {
                    $step = 1;
                    $rewindClosure();
                } else {
                    $step = 6;
                }
            },
            'valid' => function () use (&$step, $result) {
            
                return $step < 6;
            },
            'key' => function ($key) use (&$step) {
            
                switch($step){
                    case 1:
                        return 'tableHeadLine';
                        
                    case 2:
                        return 'header';
                        
                    case 3:
                        return 'headerFootLine';
                        
                    case 4:
                        return $key;
                        
                    case 5:
                        return 'tableFootLine';
                    
                    default:
                        return null;
                }
            },
            'current' => function ($row) use (&$step, $result, $writer) {
            
                switch($step){
                    case 1:
                        return $writer->getFillerLine();
                
                    case 2:
                        $headers = array();
                        foreach ($result->getHeaders() as $header) {
                            $headers[$header] = $header;
                        }
                        return $writer->getRowAsTableRow($headers);
                
                    case 3:
                        return $writer->getFillerLine();
                
                    case 4:
                        if (is_array($row)) {
                            return $writer->getRowAsTableRow($row);
                        } else {
                            $step++;
                        }
                        
                    case 5:
                        return $writer->getFillerLine();
                            
                    default:
                        return null;
                }
            },
            'next' => function ($nextClosure) use (&$step, $iterator) {
            
                if ($step === 4) {
                    $nextClosure();
                    if ($iterator->valid()) {
                        return;
                    }
                }
                
                $step++;
            }
        ]);
    }

    public function __toString()
    {
        $output = "";
        foreach ($this->getIterator() as $line) {
            $output .= $line;
        }
        return $output;
    }
}
