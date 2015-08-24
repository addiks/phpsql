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
use Addiks\PHPSQL\ResultWriter;

class SelectTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");

        $this->pdo->query("
            CREATE TABLE `phpunit_select_first` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32),
                baz DATETIME
            )
        ");

        $this->pdo->query("
            CREATE TABLE `phpunit_select_second` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                first_id INT,
                boo INT,
                far VARCHAR(32),
                faz INT,
                FOREIGN KEY (first_id) REFERENCES `phpunit_select_first` (id)
            )
        ");

        $insertStatement = $this->pdo->prepare("
            INSERT INTO `phpunit_select_first`
                (foo, bar, baz)
            VALUES
                (?, ?, ?)
        ");

        foreach ([
            ["123", "Lorem ipsum", "1990-03-18 18:03:09"],
            ["456", "dolor",       "1984-10-26 00:00:00"],
            ["789", "sit amet",    "2029-06-15 12:00:00"],
        ] as $parameters) {
            $insertStatement->execute($parameters);
        }

        $insertStatement = $this->pdo->prepare("
            INSERT INTO `phpunit_select_second`
                (first_id, boo, far, faz)
            VALUES
                (?, ?, ?, ?)
        ");

        foreach ([
            ["1", "147", "consetetur", 12],
            ["1", "258", "sadipscing", 15],
            ["3", "369", "elitr",      18],
        ] as $parameters) {
            $insertStatement->execute($parameters);
        }

    }

    /**
     * @group behaviour.select
     */
    public function testSelectSimple()
    {
        
        ### EXECUTE

        try {
            $result = $this->pdo->query("SELECT foo, bar, baz FROM `phpunit_select_first` ORDER BY baz DESC");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["789", "sit amet",    "2029-06-15 12:00:00"],
            ["123", "Lorem ipsum", "1990-03-18 18:03:09"],
            ["456", "dolor",       "1984-10-26 00:00:00"],
        ], $actualRows);
    }

    /**
     * @depends testSelectSimple
     * @group behaviour.select
     */
    public function testSelectWhere()
    {
        
        ### EXECUTE

        try {
            $result = $this->pdo->query("
                SELECT foo, bar, baz
                FROM `phpunit_select_first`
                WHERE foo > 400
                ORDER BY baz DESC
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["789", "sit amet",    "2029-06-15 12:00:00"],
            ["456", "dolor",       "1984-10-26 00:00:00"],
        ], $actualRows);
    }

    /**
     * @depends testSelectWhere
     * @group behaviour.select
     */
    public function testSelectHaving()
    {
        ### EXECUTE

        try {
            $result = $this->pdo->query("
                SELECT foo, bar, baz
                FROM `phpunit_select_first`
                HAVING foo > 400
                ORDER BY baz DESC
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["789", "sit amet",    "2029-06-15 12:00:00"],
            ["456", "dolor",       "1984-10-26 00:00:00"],
        ], $actualRows);
    }

    /**
     * @depends testSelectSimple
     * @group behaviour.select
     */
    public function testSelectJoin()
    {
        
        ### EXECUTE

        try {
            $result = $this->pdo->query("
                SELECT
                    *
                FROM 
                          `phpunit_select_first`  as `f`
                LEFT JOIN `phpunit_select_second` as `s` ON(f.id = s.first_id)
                ORDER BY f.id, s.id
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["1", "123", "Lorem ipsum", "1990-03-18 18:03:09",    "1", "1", "147", "consetetur", "12"],
            ["1", "123", "Lorem ipsum", "1990-03-18 18:03:09",    "2", "1", "258", "sadipscing", "15"],
            ["3", "789", "sit amet",    "2029-06-15 12:00:00",    "3", "3", "369", "elitr",      "18"],
        ], $actualRows);
    }

    /**
     * @depends testSelectJoin
     * @group behaviour.select
     */
    public function testSelectSubQuery()
    {
        ### EXECUTE

        try {
            $result = $this->pdo->query("
                SELECT
                    *
                FROM 
                    (SELECT id, foo FROM `phpunit_select_first` WHERE foo > 200) as `a`
                ORDER BY a.id
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["2", "456"],
            ["3", "789"],
        ], $actualRows);
    }

    /**
     * @depends testSelectSimple
     * @group behaviour.select
     */
    public function testSelectGroupBy()
    {
        ### EXECUTE

        try {
            $result = $this->pdo->query("
                SELECT
                    first_id,
                    SUM(boo) as sum
                FROM
                    `phpunit_select_second`
                GROUP BY
                    first_id
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["1", "405"],
            ["3", "369"],
        ], $actualRows);
    }

    /**
     * @depends testSelectSimple
     * @group behaviour.select
     */
    public function testSelectUnion()
    {

        ### EXECUTE

        try {
            $result = $this->pdo->query("
                      SELECT '123'
                UNION SELECT '456'
                UNION SELECT '789'
            ");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["123"],
            ["456"],
            ["789"],
        ], $actualRows);
    }
}
