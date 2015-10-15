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
use Addiks\PHPSQL\PDO\PDO;
use Addiks\PHPSQL\Result\ResultWriter;

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
            ["id",  "int(4)",      "NO",  "PRI", "", "auto_increment"],
            ["foo", "int(4)",      "YES", "",    "", ""],
            ["bar", "varchar(32)", "YES", "",    "", ""],
            ["baz", "datetime",    "YES", "",    "", ""],
        ], $actualRows);
    }
}
