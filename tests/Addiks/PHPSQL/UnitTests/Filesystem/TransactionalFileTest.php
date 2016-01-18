<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\Tests\UnitTests\Filesystem;

use PHPUnit_Framework_TestCase;
use Addiks\PHPSQL\Filesystem\TransactionalFile;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;

class TransactionalFileTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->realFile = new FileResourceProxy(fopen("php://memory", "w"));

        $this->file = new TransactionalFile($this->realFile);
    }

    protected $realFile;

    protected $file;

    public function testClose()
    {
        $this->markTestIncomplete(); # What should actually happen here?
    }

    /**
     * @ depends testWriteRead
     * @group unittests.transaction.file
     * @group unittests.transaction.file.write_commit
     * @dataProvider dataProviderWriteCommitAndRollback
     */
    public function testWriteCommit($fixtureText, $committedText)
    {
        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $fixtureTextLength   = strlen($fixtureText);
        $committedTextLength = strlen($committedText);
        $combinedLength      = $fixtureTextLength + $committedTextLength;

        ### EXECUTE

        $this->assertEquals(0, $file->getLength());
        $this->assertEquals(0, $realFile->getLength());

        $file->write($fixtureText);

        $this->assertEquals($fixtureTextLength, $file->getLength());
        $this->assertEquals($fixtureTextLength, $realFile->getLength());

        $file->beginTransaction();
        $file->write($committedText);

        $this->assertEquals($combinedLength, $file->getLength());
        $this->assertEquals($fixtureTextLength, $realFile->getLength());

        $file->commit();

        $this->assertEquals($combinedLength, $file->getLength());
        $this->assertEquals($combinedLength, $realFile->getLength());
    }

    /**
     * @depends testWriteRead
     * @group unittests.transaction.file
     * @dataProvider dataProviderWriteCommitAndRollback
     */
    public function testWriteRollback($fixtureText, $committedText)
    {
        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $fixtureTextLength   = strlen($fixtureText);
        $committedTextLength = strlen($committedText);
        $combinedLength      = $fixtureTextLength + $committedTextLength;

        ### EXECUTE

        $this->assertEquals(0, $file->getLength());
        $this->assertEquals(0, $realFile->getLength());

        $file->write($fixtureText);

        $this->assertEquals($fixtureTextLength, $file->getLength());
        $this->assertEquals($fixtureTextLength, $realFile->getLength());

        $file->beginTransaction();
        $file->write($committedText);

        $this->assertEquals($combinedLength, $file->getLength());
        $this->assertEquals($fixtureTextLength, $realFile->getLength());

        $file->rollback();

        $this->assertEquals($fixtureTextLength, $file->getLength());
        $this->assertEquals($fixtureTextLength, $realFile->getLength());
    }

    public function dataProviderWriteCommitAndRollback()
    {
        return array(
            ["Lorem ipsum", ", dolor sit amet"],
            ["1", "2"],
            ["A", ""],
            ["", ""],
            ["ABC\0\n\rDEF", "GHI\0\n\rJKL"],
        #    [str_pad("", 32*1024, "A"), str_pad("", 32*1024, "A")],
        );
    }

    /**
     * @group unittests.transaction.file
     * @dataProvider dataProviderWriteRead
     */
    public function testWriteRead($expectedText)
    {
        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        ### EXECUTE

        $file->write($expectedText);

        $file->seek(0, SEEK_SET);

        $actualText = $file->read();

        $this->assertEquals($expectedText, $actualText);
    }

    public function dataProviderWriteRead()
    {
        return array(
            ["Lorem ipsum"],
            ["1"],
            [""],
            ["ABC\0DEF\nGHI\rJKL"],
        );
    }

    /**
     * @group unittests.transaction.file
     */
    public function testTruncate()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testSeek()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testTell()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testEof()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testLock()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testFlush()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testGetSize()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testReadLine()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testGetData()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testSetData()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testAddData()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testGetLength()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testBeginTransaction()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testCommit()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

    /**
     * @group unittests.transaction.file
     */
    public function testRollback()
    {
        $this->markTestIncomplete();

        ### PREPARE

        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        ### EXECUTE
    }

}
