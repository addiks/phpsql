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

trait BinaryConverterTrait
{
    
    public function decstr($dec, $targetLength = null)
    {
        $str = "";
        
        if ($dec === 0) {
            $str = "\0";
            
        } else {
            $hex = dechex($dec);
            if (strlen($hex) % 2 !== 0) {
                $hex = "0{$hex}";
            }
            foreach (str_split($hex, 2) as $hexChar) {
                $str .= chr(hexdec($hexChar));
            }
        }
        
        if (is_int($targetLength)) {
            $str = substr($str, 0, $targetLength);
            $str = str_pad($str, $targetLength, "\0", STR_PAD_LEFT);
        }
        return $str;
    }
    
    public function strdec($str, $trimNullBytes = true)
    {
        
        if ($trimNullBytes) {
            $str = ltrim($str, "\0");
        }
        
        $integer = 0;
        
        $length = strlen($str);
        for ($index=0; $index<$length; ++$index) {
            $integer += ord($str[$length - $index -1]) * pow(256, $index);
        }
        
        return $integer;
    }
    
    public function stringIncrement(&$string)
    {
    
        $position = strlen($string)-1;
    
        do {
            if ($position < 0) {
                $string = chr(0x01) . $string;
                return;
            }
    
            $decimal = ord($string[$position]);
            $decimal++;
    
            $newDecimal = $decimal % 256;
    
            $string  = substr($string, 0, $position);
            $string .= chr($newDecimal);
            $string .= substr($string, $position+1);
    
            $position--;
    
        } while ($decimal > 255);
    }
    
    public function stringDecrement(&$string)
    {
    
        $position = strlen($string)-1;
    
        do {
            if ($position < 0) {
                $string = substr($string, 1);
                return;
            }
    
            $decimal = ord($string[$position]);
            $decimal--;
    
            $newDecimal = $decimal < 0 ?255 :$decimal;
    
            $string  = substr($string, 0, $position);
            $string .= chr($newDecimal);
            $string .= substr($string, $position+1);
    
            $position--;
    
        } while ($decimal < 0);
    }
}
