<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\BehaviorTests;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\PDO;

class InsertIntoTest extends PHPUnit_Framework_TestCase
{
    
    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");
    }

    /**
     * @group behaviour.insert_into
     */
    public function testInsertInto()
    {

        ### PREPARE

        $this->pdo->query("
            CREATE TABLE `phpunit_insertintotest` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32),
                baz DATETIME
            )
        ");

        ### EXECUTE

        $this->pdo->query("
            INSERT INTO `phpunit_insertintotest`
                (foo, bar, baz)
            VALUES
                (12, 'Lorem ipsum',           '2012-03-04 05:06:07'),
                (34, 'dolor sit amet',        '2008-09-10 11:12:13'),
                (56, 'consetetur sadipscing', '2014-05-16 17:18:19')
        ");

        ### CHECK RESULTS

        $result = $this->pdo->query("SELECT foo, bar, baz FROM `phpunit_insertintotest` ORDER BY foo ASC");

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ['12', 'Lorem ipsum',           '2012-03-04 05:06:07'],
            ['34', 'dolor sit amet',        '2008-09-10 11:12:13'],
            ['56', 'consetetur sadipscing', '2014-05-16 17:18:19'],
        ], $actualRows);
    }

}
