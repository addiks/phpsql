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
use Addiks\PHPSQL\Entity\Exception\MalformedSql;

class UpdateTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }
    
    public function testUpdate()
    {
        ### PREPARE

        $this->pdo->query("
            CREATE TABLE updateTest (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                username VARCHAR(32),
                `password` VARCHAR(32)
            );
            INSERT INTO updateTest
                (username, `password`)
            VALUES
                ('max',    '123456'),
                ('moritz', 'geheim')
        ");

        ### EXECUTE

        try {
            $this->pdo->query("
                UPDATE updateTest
                SET `password` = 'secret'
                WHERE username = 'moritz'
            ");
        } catch(MalformedSql $exception) {
            echo $exception;
            throw $exception;
        }

        $result = $this->pdo->query("SELECT * FROM updateTest ORDER BY id ASC");
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["1", "max",    "123456"],
            ["2", "moritz", "secret"],
        ], $actualRows);
    }
}
