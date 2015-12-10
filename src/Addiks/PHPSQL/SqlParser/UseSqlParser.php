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

namespace Addiks\PHPSQL\SqlParser;

use Addiks\PHPSQL\Iterators\TokenIterator;
use Addiks\PHPSQL\Value\Enum\Sql\SqlToken;
use Addiks\PHPSQL\Exception\MalformedSqlException;
use Addiks\PHPSQL\Iterators\SQLTokenIterator;
use Addiks\PHPSQL\SqlParser\SqlParser;
use Addiks\PHPSQL\Job\Statement\UseStatement;
use Addiks\PHPSQL\SqlParser\Part\ValueParser;
use Addiks\PHPSQL\Value\Sql\Variable;
use Addiks\PHPSQL\Job\Part\ValuePart;

class UseSqlParser extends SqlParser
{

    protected $valueParser;

    public function getValueParser()
    {
        return $this->valueParser;
    }

    public function setValueParser(ValueParser $valueParser)
    {
        $this->valueParser = $valueParser;
    }

    public function canParseTokens(SQLTokenIterator $tokens)
    {
        return is_int($tokens->isTokenNum(SqlToken::T_USE(), TokenIterator::CURRENT))
            || is_int($tokens->isTokenNum(SqlToken::T_USE(), TokenIterator::NEXT));
    }

    public function convertSqlToJob(SQLTokenIterator $tokens)
    {

        $tokens->seekTokenNum(SqlToken::T_USE());

        if ($tokens->getCurrentTokenNumber() !== SqlToken::T_USE()) {
            throw new MalformedSqlException("Tried to parse USE statement when token-iterator does not point to T_USE!", $tokens);
        }

        if ($tokens->seekTokenNum(T_VARIABLE)) {
            $databaseName = Variable::factory($tokens->getCurrentTokenString());

        } elseif ($tokens->seekTokenNum(T_STRING)) {
            $databaseName = $tokens->getCurrentTokenString();

        } elseif ($tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)) {
            $databaseName = $tokens->getCurrentTokenString();

            if (($databaseName[0] === '"' || $databaseName[0] === "'")
             && $databaseName[0] === $databaseName[strlen($databaseName)-1]) {
                // remove quotes if needed
                $databaseName = substr($databaseName, 1, strlen($databaseName)-2);
            }

        } else {
            throw new MalformedSqlException("Missing database-specifier for USE statement!", $tokens);
        }

        $databaseNameValue = new ValuePart();
        $databaseNameValue->addChainValue($databaseName);

        $useJob = new UseStatement();
        $useJob->setDatabase($databaseNameValue);

        return $useJob;
    }
}
