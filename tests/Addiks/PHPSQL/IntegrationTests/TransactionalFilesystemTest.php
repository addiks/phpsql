<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\IntegrationTests;

use PHPUnit_Framework_Testcase;
use Addiks\PHPSQL\Filesystem\InmemoryFilesystem;
use Addiks\PHPSQL\Filesystem\TransactionalFilesystem;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;

class TransactionalFilesystemTest extends PHPUnit_Framework_Testcase
{

    public function setUp()
    {
        $this->realFilesystem = new InmemoryFilesystem();

        $this->filesystem = new TransactionalFilesystem($this->realFilesystem);
    }

    protected $filesystem;

    protected $realFilesystem;

    /**
     * @group integration.transaction.filesystem
     * @group integration.transaction.filesystem.get_file_contents
     * @group integration.transaction.filesystem.put_file_contents
     * @dataProvider dataProviderPutFileContents
     */
    public function testPutGetFileContents(
        array $fixtureFiles,
        array $firstPutFiles,
        array $secondPutFiles,
        $doFirstCommit,
        array $firstExpectedFiles,
        $doSecondCommit,
        array $secondExpectedFiles
    ) {
        /* @var $filesystem TransactionalFilesystem */
        $filesystem = $this->filesystem;

        /* @var $realFilesystem FilesystemInterface */
        $realFilesystem = $this->realFilesystem;

        foreach ($fixtureFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        foreach ($fixtureFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }

        $filesystem->beginTransaction();

        foreach ($firstPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        foreach ($firstPutFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }

        $filesystem->beginTransaction();

        foreach ($secondPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        foreach ($secondPutFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }

        if ($doFirstCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($firstExpectedFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }

        if ($doSecondCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($secondExpectedFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }
    }

    public function dataProviderPutFileContents()
    {
        return array(
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                    '/foo/faz' => null,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "dolor sit amet",
                    '/foo/faz' => "elitr, sed diam",
                ],
                true,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "dolor sit amet",
                    '/foo/faz' => "elitr, sed diam",
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/baz' => "dolor sit amet",
                    '/foo/faz' => "elitr, sed diam",
                ],
                false,
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                    '/foo/faz' => null,
                ],
            ],
        );
    }

    /**
     * @group integration.transaction.filesystem
     * @group integration.transaction.filesystem.file_size
     * @dataProvider dataProviderFileSize
     */
    public function testFileSize(
        array $fixtureFiles,
        array $firstPutFiles,
        array $secondPutFiles,
        $doFirstCommit,
        array $firstExpectedFiles,
        $doSecondCommit,
        array $secondExpectedFiles
    ) {
        /* @var $filesystem TransactionalFilesystem */
        $filesystem = $this->filesystem;

        /* @var $realFilesystem FilesystemInterface */
        $realFilesystem = $this->realFilesystem;

        foreach ($fixtureFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        foreach ($fixtureFiles as $filePath => $fileData) {
            $this->assertEquals($fileData, $filesystem->getFileContents($filePath));
        }

        $filesystem->beginTransaction();

        foreach ($firstPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        $filesystem->beginTransaction();

        foreach ($secondPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        if ($doFirstCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($firstExpectedFiles as $filePath => $fileSize) {
            $this->assertEquals($fileSize, $filesystem->fileSize($filePath));
        }

        if ($doSecondCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($secondExpectedFiles as $filePath => $fileSize) {
            $this->assertEquals($fileSize, $filesystem->fileSize($filePath));
        }
    }

    public function dataProviderFileSize()
    {
        return array(
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 13,
                    '/foo/faz' => 15,
                ],
                true,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 13,
                    '/foo/faz' => 15,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 13,
                    '/foo/faz' => 15,
                ],
                false,
                [
                    '/foo/bar' => 11,
                    '/foo/baz' => 14,
                    '/foo/faz' => null,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 14,
                    '/foo/faz' => 15,
                ],
                true,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 14,
                    '/foo/faz' => 15,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => 21,
                    '/foo/baz' => 14,
                    '/foo/faz' => 15,
                ],
                false,
                [
                    '/foo/bar' => 11,
                    '/foo/baz' => 14,
                    '/foo/faz' => null,
                ],
            ],
        );
    }

    /**
     * @group integration.transaction.filesystem
     * @group integration.transaction.filesystem.file_exists
     * @dataProvider dataProviderFileExists
     */
    public function testFileExists(
        array $fixtureFiles,
        array $firstPutFiles,
        array $secondPutFiles,
        $doFirstCommit,
        array $firstExpectedFiles,
        $doSecondCommit,
        array $secondExpectedFiles
    ) {
        /* @var $filesystem TransactionalFilesystem */
        $filesystem = $this->filesystem;

        /* @var $realFilesystem FilesystemInterface */
        $realFilesystem = $this->realFilesystem;

        foreach ($fixtureFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        $filesystem->beginTransaction();

        foreach ($firstPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        $filesystem->beginTransaction();

        foreach ($secondPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        if ($doFirstCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($firstExpectedFiles as $filePath => $fileExists) {
            $this->assertEquals($fileExists, $filesystem->fileExists($filePath), $filePath);
        }

        if ($doSecondCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($secondExpectedFiles as $filePath => $fileExists) {
            $this->assertEquals($fileExists, $filesystem->fileExists($filePath));
        }

    }

    public function dataProviderFileExists()
    {
        return array(
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => true,
                ],
                true,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => true,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/raz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/raz' => false,
                ],
                true,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => true,
                    '/foo/raz' => false,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => true,
                ],
                false,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => false,
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/faz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => true,
                ],
                false,
                [
                    '/foo/bar' => true,
                    '/foo/baz' => true,
                    '/foo/faz' => false,
                ],
            ],
        );
    }

    /**
     * @group integration.transaction.filesystem
     * @group integration.transaction.filesystem.get_files_in_dir
     * @dataProvider dataProviderGetFilesInDir
     */
    public function testGetFilesInDir(
        array $fixtureFiles,
        array $firstPutFiles,
        array $secondPutFiles,
        $doFirstCommit,
        array $firstExpectedFiles,
        $doSecondCommit,
        array $secondExpectedFiles
    ) {
        /* @var $filesystem TransactionalFilesystem */
        $filesystem = $this->filesystem;

        /* @var $realFilesystem FilesystemInterface */
        $realFilesystem = $this->realFilesystem;

        foreach ($fixtureFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        $filesystem->beginTransaction();

        foreach ($firstPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        $filesystem->beginTransaction();

        foreach ($secondPutFiles as $filePath => $fileData) {
            $filesystem->putFileContents($filePath, $fileData);
        }

        if ($doFirstCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($firstExpectedFiles as $filePath => $filesInDir) {
            $this->assertEquals(
                $filesInDir,
                $filesystem->getFilesInDir($filePath),
                $filePath,
                0.0,
                10,
                true
            );
        }

        if ($doSecondCommit) {
            $filesystem->commit();

        } else {
            $filesystem->rollback();
        }

        foreach ($secondExpectedFiles as $filePath => $filesInDir) {
            $this->assertEquals(
                $filesInDir,
                $filesystem->getFilesInDir($filePath),
                $filePath,
                0.0,
                10,
                true
            );
        }

    }

    public function dataProviderGetFilesInDir()
    {
        return array(
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/raz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo' => ['bar', 'baz', 'faz', 'raz'],
                ],
                true,
                [
                    '/foo' => ['bar', 'baz', 'faz', 'raz'],
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/raz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo' => ['bar', 'baz', 'faz'],
                ],
                true,
                [
                    '/foo' => ['bar', 'baz', 'faz'],
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/raz' => "tempor invidunt",
                ],
                true,
                [
                    '/foo' => ['bar', 'baz', 'faz', 'raz'],
                ],
                false,
                [
                    '/foo' => ['bar', 'baz'],
                ],
            ],
            [
                [
                    '/foo/bar' => "Lorem ipsum",
                    '/foo/baz' => "dolor sit amet",
                ],
                [
                    '/foo/bar' => "consetetur sadipscing",
                    '/foo/faz' => "elitr, sed diam",
                ],
                [
                    '/foo/baz' => "nonumy eirmod",
                    '/foo/raz' => "tempor invidunt",
                ],
                false,
                [
                    '/foo' => ['bar', 'baz', 'faz'],
                ],
                false,
                [
                    '/foo' => ['bar', 'baz'],
                ],
            ],
        );
    }

}
