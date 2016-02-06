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

        $this->file = new TransactionalFile($this->realFile, null, 4);
    }

    protected $realFile;

    protected $file;

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.write_read
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

        $actualText = $file->read(strlen($expectedText));

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
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.truncate
     * @dataProvider dataProviderTruncate
     */
    public function testTruncate($fixtureData, $truncatePosition, $expectedData, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $this->assertEquals(strlen($fixtureData), $file->getLength());
        $this->assertEquals(strlen($fixtureData), $realFile->getLength());

        $file->beginTransaction();

        $file->truncate($truncatePosition);

        $this->assertEquals($truncatePosition, $file->getLength());
        $this->assertEquals(strlen($fixtureData), $realFile->getLength());

        if ($doCommit) {
            $file->commit();

            $this->assertEquals($truncatePosition, $file->getLength());
            $this->assertEquals($truncatePosition, $realFile->getLength());
            $this->assertEquals($expectedData, $file->getData());
            $this->assertEquals($expectedData, $realFile->getData());

        } else {
            $file->rollback();

            $this->assertEquals(strlen($fixtureData), $file->getLength());
            $this->assertEquals(strlen($fixtureData), $realFile->getLength());
            $this->assertEquals($expectedData, $file->getData());
            $this->assertEquals($expectedData, $realFile->getData());
        }
    }

    public function dataProviderTruncate()
    {
        return array(
            ['Lorem ipsum', 5, 'Lorem',       true],
            ['Lorem ipsum', 5, 'Lorem ipsum', false],
            ['a', 0, '',  true],
            ['a', 0, 'a', false],
            ["a\0b", 0, "",     true],
            ["a\0b", 0, "a\0b", false],
            ["a\0b", 1, "a",    true],
            ["a\0b", 1, "a\0b", false],
            ["a\0b", 2, "a\0",  true],
            ["a\0b", 2, "a\0b", false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.seek
     * @group unittests.transaction.file.tell
     * @dataProvider dataProviderSeekAndTell
     */
    public function testSeekAndTell($fixtureData, $fixtureSeek, $seekPosition, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $file->seek($fixtureSeek);

        $this->assertEquals($fixtureSeek, $file->tell());
        $this->assertEquals($fixtureSeek, $realFile->tell());

        $file->beginTransaction();

        $file->seek($seekPosition);

        $this->assertEquals($seekPosition, $file->tell());
        $this->assertEquals($fixtureSeek, $realFile->tell());

        if ($doCommit) {
            $file->commit();

            $this->assertEquals($seekPosition, $file->tell());
            $this->assertEquals($seekPosition, $realFile->tell());

        } else {
            $file->rollback();

            $this->assertEquals($fixtureSeek, $file->tell());
            $this->assertEquals($fixtureSeek, $realFile->tell());
        }
    }

    public function dataProviderSeekAndTell()
    {
        return array(
            ["Lorem ipsum", 5, 7, true],
            ["Lorem ipsum", 5, 7, false],
            ["1", 1, 0, true],
            ["1", 1, 0, false],
            ["", 0, 0, true],
            ["", 0, 0, false],
            ["\0\0", 0, 1, true],
            ["\0\0", 0, 1, false],
            ["\0\0", 1, 0, true],
            ["\0\0", 1, 0, false],
            ["\0\0\0", 0, 1, true],
            ["\0\0\0", 0, 1, false],
            ["\0\0\0", 1, 2, true],
            ["\0\0\0", 1, 2, false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.readline
     * @dataProvider dataProviderReadLine
     */
    public function testReadLine($fixtureData, $fixtureSeek, $expectedFirstLine, $expectedSecondLine, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        $file->setData($fixtureData);
        $file->seek($fixtureSeek);

        $file->beginTransaction();

        $actualFirstLine = $file->readLine();

        if ($doCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $actualSecondLine = $file->readLine();

        $this->assertEquals($expectedFirstLine, $actualFirstLine);
        $this->assertEquals($expectedSecondLine, $actualSecondLine);
    }

    public function dataProviderReadLine()
    {
        return array(
            ["Lorem ipsum\ndolor sit\namet", 6, "ipsum\n", "dolor sit\n", true],
            ["Lorem ipsum\ndolor sit\namet", 6, "ipsum\n", "ipsum\n", false],
            ["a\0b\nc\0d", 1, "\0b\n", "c\0d", true],
            ["a\0b\nc\0d", 1, "\0b\n", "\0b\n", false],
            ["a\0b", 1, "\0b", "", true],
            ["a\0b", 1, "\0b", "\0b", false],
            ["a\0b", 0, "a\0b", "", true],
            ["a\0b", 0, "a\0b", "a\0b", false],
            ["a\0b", 3, "", "", true],
            ["a\0b", 3, "", "", false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.getdata
     * @dataProvider dataProviderGetData
     */
    public function testGetData($fixtureData, $fixtureSeek, $fixtureWrite, $expectedData, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $file->beginTransaction();

        $file->seek($fixtureSeek);
        $file->write($fixtureWrite);

        if ($doCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $this->assertEquals($expectedData, $file->getData());
        $this->assertEquals($expectedData, $realFile->getData());
    }

    public function dataProviderGetData()
    {
        return array(
            ["Lorem ipsum", 6, "dolor", "Lorem dolor", true],
            ["Lorem ipsum", 6, "dolor", "Lorem ipsum", false],
            ["a\0b", 2, "c\0d", "a\0c\0d", true],
            ["a\0b", 2, "c\0d", "a\0b", false],
            ["", 0, "a", "a", true],
            ["", 0, "a", "", false],
            ["", 0, "", "", true],
            ["", 0, "", "", false],
            ["", 1, "a", "\0a", true],
            ["", 1, "a", "", false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.setdata
     * @dataProvider dataProviderSetData
     */
    public function testSetData($fixtureData, $expectedData, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $file->beginTransaction();

        $file->setData($expectedData);

        $this->assertEquals($expectedData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        if ($doCommit) {
            $file->commit();

            $this->assertEquals($expectedData, $file->getData());
            $this->assertEquals($expectedData, $realFile->getData());

        } else {
            $file->rollback();

            $this->assertEquals($fixtureData, $file->getData());
            $this->assertEquals($fixtureData, $realFile->getData());
        }
    }

    public function dataProviderSetData()
    {
        return array(
            ['Lorem', 'ipsum', true],
            ['Lorem', 'ipsum', false],
            ["a\0b", "c\0d", true],
            ["a\0b", "c\0d", false],
            ["", "c\0d", true],
            ["", "c\0d", false],
            ["a\0b", "", true],
            ["a\0b", "", false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.adddata
     * @dataProvider dataProviderAddData
     */
    public function testAddData($fixtureData, $addData, $expectedData, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $this->assertEquals($fixtureData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        $file->beginTransaction();

        $file->addData($addData);

        $this->assertEquals($fixtureData.$addData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        if ($doCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $this->assertEquals($expectedData, $file->getData());
        $this->assertEquals($expectedData, $realFile->getData());
    }

    public function dataProviderAddData()
    {
        return array(
            ["Lorem", " ipsum", "Lorem ipsum", true],
            ["Lorem", " ipsum", "Lorem", false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.getlength
     * @dataProvider dataProviderGetLength
     */
    public function testGetLength($fixtureData, $addData, $expectedLength, $doCommit)
    {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $this->assertEquals($fixtureData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        $file->beginTransaction();

        $file->addData($addData);

        if ($doCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $this->assertEquals($expectedLength, $file->getLength());
        $this->assertEquals($expectedLength, $realFile->getLength());
    }

    public function dataProviderGetLength()
    {
        return array(
            ["Lorem", " ipsum", 11, true],
            ["Lorem", " ipsum", 5, false],
            ["a\0b", "c\0d", 6, true],
            ["a\0b", "c\0d", 3, false],
            ["a\0b", "", 3, true],
            ["a\0b", "", 3, false],
            ["", "c\0d", 3, true],
            ["", "c\0d", 0, false],
        );
    }

    /**
     * @group unittests.transaction.file
     * @group unittests.transaction.file.cascaded_transactions
     * @dataProvider dataProviderCascadedTransactions
     */
    public function testCascadedTransactions(
        $fixtureData,
        $firstSeek,
        $firstData,
        $secondSeek,
        $secondData,
        $doFirstCommit,
        $firstExpectedData,
        $doSecondCommit,
        $secondExpectedData
    ) {
        /* @var $file TransactionalFile */
        $file = $this->file;

        /* @var $realFile FileResourceProxy */
        $realFile = $this->realFile;

        $file->setData($fixtureData);

        $this->assertEquals($fixtureData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        $file->beginTransaction();

        $file->seek($firstSeek);
        $file->write($firstData);

        $this->assertEquals($fixtureData, $realFile->getData());

        $file->beginTransaction();

        $file->getData();

        $file->seek($secondSeek);
        $file->write($secondData);

        $this->assertEquals($fixtureData, $realFile->getData());

        $file->getData();

        if ($doFirstCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $this->assertEquals($firstExpectedData, $file->getData());
        $this->assertEquals($fixtureData, $realFile->getData());

        if ($doSecondCommit) {
            $file->commit();

        } else {
            $file->rollback();
        }

        $this->assertEquals($secondExpectedData, $file->getData());
        $this->assertEquals($secondExpectedData, $realFile->getData());

    }

    public function dataProviderCascadedTransactions()
    {
        return array(
            [
                "Lorem ipsum",
                2,
                "foo",
                6,
                "bar",
                true,
                "Lofoo barum",
                true,
                "Lofoo barum"
            ],
            [
                "Lorem ipsum",
                2,
                "foo",
                6,
                "bar",
                true,
                "Lofoo barum",
                false,
                "Lorem ipsum"
            ],
            [
                "Lorem ipsum",
                2,
                "foo",
                6,
                "bar",
                false,
                "Lofoo ipsum",
                true,
                "Lofoo ipsum"
            ],
            [
                "Lorem ipsum",
                2,
                "foo",
                6,
                "bar",
                false,
                "Lofoo ipsum",
                false,
                "Lorem ipsum"
            ],
        );
    }

}
