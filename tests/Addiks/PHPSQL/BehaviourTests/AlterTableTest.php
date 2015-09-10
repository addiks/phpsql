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

class AlterTableTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->pdo = new PDO("inmemory:phpunit");

        $this->pdo->query("
            CREATE TABLE `phpunit_alter_table` (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                foo INT,
                bar VARCHAR(32),
                baz DATETIME
            )
        ");

    }

    /**
     * @group behaviour.alter
     * @group behaviour.alter.add
     */
    public function testAddSimpleColumn()
    {
        ### EXECUTE

        try {
            $this->pdo->query("ALTER TABLE `phpunit_alter_table` ADD COLUMN faz TINYINT(1) DEFAULT 1");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $result = $this->pdo->query("DESCRIBE `phpunit_alter_table`");
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["id",  "int(4)",      "NO",  "PRI", "",  "auto_increment"],
            ["foo", "int(4)",      "YES", "",    "",  ""],
            ["bar", "varchar(32)", "YES", "",    "",  ""],
            ["baz", "datetime",    "YES", "",    "",  ""],
            ["faz", "tinyint(1)",  "YES", "",    "1", ""],
        ], $actualRows);
    }

    /**
     * @group behaviour.alter
     * @group behaviour.alter.remove
     */
    public function testRemoveColumn()
    {
        ### EXECUTE

        try {
            $this->pdo->query("ALTER TABLE `phpunit_alter_table` DROP COLUMN bar");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $result = $this->pdo->query("DESCRIBE `phpunit_alter_table`");
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["id",  "int(4)",   "NO",  "PRI", "",  "auto_increment"],
            ["foo", "int(4)",   "YES", "",    "",  ""],
            ["baz", "datetime", "YES", "",    "",  ""],
        ], $actualRows);
    }

    /**
     * @group behaviour.alter
     * @group behaviour.alter.modify
     * @group DEV
     */
    public function testModifyColumn()
    {
        ### EXECUTE

        try {
            $this->pdo->query("ALTER TABLE `phpunit_alter_table` MODIFY COLUMN bar MEDIUMTEXT NOT NULL DEFAULT 'test'");
        } catch(Exception $exception) {
            throw $exception;
        }

        ### CHECK RESULTS

        $result = $this->pdo->query("DESCRIBE `phpunit_alter_table`");
        
        $actualRows = $result->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([
            ["id",  "int(4)",     "NO",  "PRI", null,    "auto_increment"],
            ["foo", "int(4)",     "YES", "",    null,    ""],
            ["bar", "mediumtext", "NO",  "",    "test",  ""],
            ["baz", "datetime",   "YES", "",    null,    ""],
        ], $actualRows);
    }
}
