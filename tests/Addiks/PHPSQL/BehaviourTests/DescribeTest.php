<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviourTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO;
use Addiks\PHPSQL\ResultWriter;

class DescribeTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");

        $this->pdo->query("
            CREATE TABLE `phpunit_describe` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32),
                baz DATETIME
            )
        ");

    }

    /**
     * @group behaviour.describe
     */
    public function testDescribe()
    {
        ### EXECUTE

        try {
            $result = $this->pdo->query("DESCRIBE `phpunit_describe`");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["id",  "INT(4)",       "NO",  "PRI", "", "auto_increment"],
            ["foo", "INT(4)",       "YES", "MUL", "", ""],
            ["bar", "VARCHAR(32)",  "YES", "MUL", "", ""],
            ["baz", "DATETIME(19)", "YES", "MUL", "", ""],
        ], $actualRows);
    }
}
