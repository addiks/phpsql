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

namespace Addiks\PHPSQL\Exception;

use Addiks\PHPSQL\Iterators\SQLTokenIterator;

use Exception;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;

class MalformedSqlException extends Exception
{

    public function __construct($message, SQLTokenIterator $tokens = null, $code = null, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (!is_null($tokens)) {
            $this->tokens = $tokens;
            $this->tokenIndex = $tokens->getIndex();
        }
    }

    private $tokens;

    /**
     * @return SQLTokenIterator
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    private $tokenIndex;

    public function getTokenIndex()
    {
        return $this->tokenIndex;
    }

    public function __toString()
    {

        /* @var $tokens SQLTokenIterator */
        $tokens = $this->getTokens();

        if (is_null($tokens)) {
            return parent::__toString();
        }

        $previousIndex = $tokens->getIndex();

        $allTokens = $tokens->getTokenArray();
        $lastToken = end($allTokens);
        $maxLineNumLength = strlen($lastToken[2]);

        if ($maxLineNumLength<2) {
            $maxLineNumLength = 2;
        }

        $tokens->seekIndex($this->getTokenIndex());

        $typeString = "";
        if ($tokens->getExclusiveTokenNumber() instanceof SqlToken) {
            $typeString = get_class($tokens->getExclusiveTokenNumber()).": ";
        } elseif (is_int($tokens->getExclusiveTokenNumber())) {
            $typeString = token_name($tokens->getExclusiveTokenNumber()).": ";
        }

        $string  = parent::__toString()."\n\n";
        $string .= " Next Token to parse: {$typeString}{$tokens->getExclusiveTokenString()} ({$tokens->getIndex()})\n";

        $line   = $tokens->getTokenArray()[$this->getTokenIndex()][2];

        $subtract = $line > 10 ?10 :$line-1;

        $column = $tokens->getColumnNumber($this->getTokenIndex()+1);
        $query  = $tokens->rebuildSqlString($line -$subtract, $line +$subtract);

        $string .= str_pad("", $column-1+$maxLineNumLength, " ")." \/\n";

        $lines = explode("\n", $query);

        // this will skip begin/end stuff if error occoured more then 15 lines away of begin/end
        for ($i=0; $i<count($lines); $i++) {
            $lineNumber = str_pad((string)($i+$line-$subtract), $maxLineNumLength, '0', STR_PAD_LEFT);

            $string .= $lineNumber.($i===$subtract ?'> ':'  ') . $lines[$i] . "\n";
        }

        $this->tokens->seekIndex($previousIndex);

        $string .= "\n";

        return $string;
    }
}
