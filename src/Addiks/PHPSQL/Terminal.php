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
use Addiks\PHPSQL\PDO\PDO;
use Addiks\PHPSQL\Database\Database;
use Addiks\PHPSQL\Result\ResultWriter;
use Addiks\PHPSQL\Exception\MalformedSqlException;

class Terminal
{

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public static function newFromPDO(PDO $pdo)
    {
        return new static($pdo->getDatabaseResource());
    }

    public function run(array $argv = array(), $stdin = STDIN, $stdout = STDOUT)
    {
        assert(is_resource($stdin));
        assert(is_resource($stdout));

        $sql = "";

        do {
            fwrite($stdout, "\n > ");
            $line = trim(fgets($stdin));

            if (in_array(strtolower($line), ['q', 'quit', 'exit', 'stop', 'esc', 'escape'])
             || feof($stdin)) {
                fwrite($stdout, "\n Bye...");
                break;
            }

            $sql .= $line . "\n";

            if (strlen($sql) > 0 && $sql[strlen($sql)-2] === ';') {
                $beforeTime = microtime(true);
                try {
                    $result = $this->database->query($sql, []);
                    fwrite($stdout, "\n");
                    if (count($result) <= 0) {
                        fwrite($stdout, " Empty result-set!\n");
                    } else {
                        fwrite($stdout, new ResultWriter($result)."\n");
                    }

                } catch (MalformedSqlException $exception) {
                    fwrite($stdout, $exception);

                } catch (InvalidArgumentException $exception) {
                    fwrite($stdout, $exception->getMessage());
                }

                $afterTime = microtime(true);
                $duration = round($afterTime - $beforeTime, 5);
                fwrite($stdout, " Took {$duration} sec\n");
                $sql = "";
            }

        } while (true);
        fwrite($stdout, "\n");
    }

}
