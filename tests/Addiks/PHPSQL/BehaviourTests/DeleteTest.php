<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Tests\BehaviourTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO\PDO;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\Result\ResultWriter;

class DeleteTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    /**
     * @group behaviour.delete
     */
    public function testDelete()
    {
        ### PREPARE

        try {
            $this->pdo->query("
                CREATE TABLE deleteTest (
                    id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                    nick VARCHAR(32),
                    `comment` TINYTEXT,
                    date_added DATETIME
                );
                INSERT INTO deleteTest
                    (nick, `comment`, date_added)
                VALUES
                    ('MrFoo',       'Lorem ipsum dolor sit amet',     NOW()),
                    ('MsBar',       '123456789012345678901234567890', NOW()),
                    ('Evil Hacker', '; DROP TABLE customers; --',     NOW()),
                    ('Baz Jr.',     'three point one four one five',  NOW())
            ");
        } catch(MalformedSql $exception) {
            echo $exception;
            throw $exception;
        }

        ### EXECUTE

        try {
            $this->pdo->query("
                DELETE FROM deleteTest
                WHERE nick = 'Evil Hacker'
            ");
        } catch(MalformedSql $exception) {
            echo $exception;
            throw $exception;
        }

        $result = $this->pdo->query("SELECT nick, `comment` FROM deleteTest ORDER BY id ASC");

#        echo "\n" . new ResultWriter($result->getResult());

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["MrFoo",   "Lorem ipsum dolor sit amet"],
            ["MsBar",   "123456789012345678901234567890"],
            ["Baz Jr.", "three point one four one five"],
        ], $actualRows);
    }
}
