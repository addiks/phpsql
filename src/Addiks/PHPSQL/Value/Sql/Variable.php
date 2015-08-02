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

namespace Addiks\PHPSQL\Value\Sql;

use ErrorException;
use Addiks\PHPSQL\Value\Text\Line;

class Variable extends Line
{
    protected function validate($string)
    {
        if (!preg_match("/^(\:[a-zA-Z0-9_-]+|\?)$/is", $string)) {
            throw new ErrorException("SQL Varaible name '{$string}' does not match pattern '^\:[a-zA-Z0-9_-]+$'!");
        }
    }

    protected $index = 0;

    public function setIndex($index)
    {
        $this->index = (int)$index;
    }

    public function getIndex()
    {
        return $this->index;
    }
}
