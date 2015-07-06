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
                    throw new InvalidArgument("Value '{$string}' is not numerical!");
                }
                return chr((int)$string);
                
            case DataType::SMALLINT():
            case DataType::MEDIUMINT():
            case DataType::INT():
            case DataType::BIGINT():
                if (!is_numeric($string)) {
                    throw new InvalidArgument("Value '{$string}' is not numerical!");
                }
                return $this->decstr((int)$string);
                
            case DataType::DEC():
            case DataType::FLOAT():
            case DataType::DOUBLE():
                if (!is_numeric($string)) {
                    throw new InvalidArgument("Value '{$string}' is not numerical!");
                }
                return $string;
            
            ### TIME / DATE
                
            case DataType::DATE():
                return $string;
            
            case DataType::DATETIME():
                return $string;
            
            case DataType::TIMESTAMP():
                return $string;
            
            case DataType::TIME():
                return $string;

            case DataType::YEAR():
                return $string;
                
            ### TEXT
            
            case DataType::CHAR():
                return $string;
            
            case DataType::VARCHAR():
                return $string;
            
            case DataType::BINARY():
                return $string;
            
            case DataType::VARBINARY():
                return $string;
            
            case DataType::TINYBLOB():
                return $string;
                
            case DataType::TINYTEXT():
                return $string;
            
            case DataType::BLOB():
                return $string;
            
            case DataType::TEXT():
                return $string;
                
            case DataType::MEDIUMBLOB():
                return $string;
                
            case DataType::MEDIUMTEXT():
                return $string;
            
            case DataType::LONGBLOB():
                return $string;
                    
            case DataType::LONGTEXT():
                return $string;
                    
            case DataType::ENUM():
                return $string;
            
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
                return chr((int)$binary);
        
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
                return $binary;
                    
            case DataType::DATETIME():
                return $binary;
                    
            case DataType::TIMESTAMP():
                return $binary;
                    
            case DataType::TIME():
                return $binary;
        
            case DataType::YEAR():
                return $binary;
        
                ### TEXT
                    
            case DataType::CHAR():
                return $binary;
                
            case DataType::VARCHAR():
                return $binary;
                
            case DataType::BINARY():
                return $binary;
                
            case DataType::VARBINARY():
                return $binary;
                
            case DataType::TINYBLOB():
                return $binary;
        
            case DataType::TINYTEXT():
                return $binary;
                
            case DataType::BLOB():
                return $binary;
                
            case DataType::TEXT():
                return $binary;
        
            case DataType::MEDIUMBLOB():
                return $binary;
        
            case DataType::MEDIUMTEXT():
                return $binary;
                
            case DataType::LONGBLOB():
                return $binary;
                
            case DataType::LONGTEXT():
                return $binary;
                
            case DataType::ENUM():
                return $binary;
                
            case DataType::SET():
                return $binary;
        }
    }
}
