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

namespace Addiks\PHPSQL;

use InvalidArgumentException;
use Addiks\PHPSQL\Value\Enum\Page\Column\DataType;

class DataConverter
{
    
    use BinaryConverterTrait;
    
    public function convertStringToBinary($string, DataType $dataType)
    {
        switch($dataType){
            
            case DataType::BIT():
                return (string)((int)$string) & 0x01;
                
            case DataType::BOOL():
                switch(strtolower($string)){
                    
                    case '1':
                    case 'true':
                        return '1';

                    default:
                    case '0':
                    case 'false':
                        return '0';
                }
            
            ### NUMERIC
                
            case DataType::TINYINT():
                if (!is_numeric($string)) {
                    throw new InvalidArgumentException("Value '{$string}' is not numerical!");
                }
                return chr((int)$string);
                
            case DataType::SMALLINT():
            case DataType::MEDIUMINT():
            case DataType::INT():
            case DataType::BIGINT():
                if (!is_numeric($string)) {
                    throw new InvalidArgumentException("Value '{$string}' is not numerical!");
                }
                return $this->decstr((int)$string);
                
            case DataType::DEC():
            case DataType::FLOAT():
            case DataType::DOUBLE():
                if (!is_numeric($string)) {
                    throw new InvalidArgumentException("Value '{$string}' is not numerical!");
                }
                return $string;
            
            ### TIME / DATE
                
            case DataType::DATE():
                return $this->decstr(strtotime($string));
            
            case DataType::DATETIME():
                return $this->decstr(strtotime($string));
            
            case DataType::TIMESTAMP():
                return $this->decstr(strtotime($string));
            
            case DataType::TIME():
                return $this->decstr(strtotime($string));

            case DataType::YEAR():
                return $this->decstr($string);
                
            ### TEXT
            
            case DataType::CHAR():
            case DataType::VARCHAR():
            case DataType::BINARY():
            case DataType::VARBINARY():
            case DataType::TINYBLOB():
            case DataType::TINYTEXT():
            case DataType::BLOB():
            case DataType::TEXT():
            case DataType::MEDIUMBLOB():
            case DataType::MEDIUMTEXT():
            case DataType::LONGBLOB():
            case DataType::LONGTEXT():
            case DataType::ENUM():
            case DataType::SET():
                return $string;
        }
    }
    
    public function convertBinaryToString($binary, DataType $dataType)
    {
        switch($dataType){
                
            case DataType::BIT():
                return $binary;
        
            case DataType::BOOL():
                break;
                    
                ### NUMERIC
        
            case DataType::TINYINT():
                return (int)ord($binary);
        
            case DataType::SMALLINT():
            case DataType::MEDIUMINT():
            case DataType::INT():
            case DataType::BIGINT():
                return $this->strdec($binary);
        
            case DataType::DEC():
            case DataType::FLOAT():
            case DataType::DOUBLE():
                return $binary;
                    
                ### TIME / DATE
        
            case DataType::DATE():
                return date("Y-m-d", $this->strdec($binary));
                    
            case DataType::DATETIME():
                return date("Y-m-d H:i:s", $this->strdec($binary));
                    
            case DataType::TIMESTAMP():
                return date("Y-m-d H:i:s", $this->strdec($binary));
                    
            case DataType::TIME():
                return date("H:i:s", $this->strdec($binary));
        
            case DataType::YEAR():
                return (string)$this->strdec($binary);
        
                ### TEXT
                    
            case DataType::CHAR():
            case DataType::VARCHAR():
            case DataType::BINARY():
            case DataType::VARBINARY():
            case DataType::TINYBLOB():
            case DataType::TINYTEXT():
            case DataType::BLOB():
            case DataType::TEXT():
            case DataType::MEDIUMBLOB():
            case DataType::MEDIUMTEXT():
            case DataType::LONGBLOB():
            case DataType::LONGTEXT():
            case DataType::ENUM():
            case DataType::SET():
                return $binary;
        }
    }
}
