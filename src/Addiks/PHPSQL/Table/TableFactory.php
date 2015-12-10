<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Table;

use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Column\ColumnDataFactoryInterface;
use Addiks\PHPSQL\Index\IndexFactoryInterface;
use Addiks\PHPSQL\Column\ColumnSchema;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Table\TableInterface;

class TableFactory implements TableFactoryInterface
{

    public function __construct(
        FilesystemInterface $filesystem,
        ColumnDataFactoryInterface $columnDataFactory
    ) {
        $this->filesystem = $filesystem;
        $this->columnDataFactory = $columnDataFactory;
    }

    protected $filesystem;

    protected $columnDataFactory;

    public function createTable(
        $schemaId,
        $tableId,
        TableSchemaInterface $tableSchema,
        IndexFactoryInterface $indexFactory
    ) {

        $autoincrementFilePath = sprintf(
            FilePathes::FILEPATH_AUTOINCREMENT,
            $schemaId,
            $tableId
        );

        $deletedRowsFilepath = sprintf(
            FilePathes::FILEPATH_DELETED_ROWS,
            $schemaId,
            $tableId
        );

        /* @var $autoincrementFile FileInterface */
        $autoincrementFile = $this->filesystem->getFile($autoincrementFilePath);

        /* @var $deletedRowsFile FileInterface */
        $deletedRowsFile = $this->filesystem->getFile($deletedRowsFilepath);

        $columnDatas = array();
        foreach ($tableSchema->getColumnIterator() as $columnId => $columnSchema) {
            /* @var $columnSchema ColumnSchema */

            $columnDatas[$columnId] = $this->columnDataFactory->createColumnData(
                $schemaId,
                $tableId,
                $columnId,
                $columnSchema
            );
        }

        $indexes = array();
        foreach ($tableSchema->getIndexIterator() as $indexId => $indexSchema) {
            /* @var $indexSchema IndexSchema */

            $indexes[$indexId] = $indexFactory->createIndex(
                $schemaId,
                $tableId,
                $indexId,
                $tableSchema,
                $indexSchema
            );
        }

        $table = new Table(
            $tableSchema,
            $columnDatas,
            $indexes,
            $autoincrementFile,
            $deletedRowsFile,
            $valueResolver = null,
            $dataConverter = null
        );

        return $table;
    }

    public function addColumnToTable(
        $schemaId,
        $tableId,
        $columnId,
        TableInterface $table,
        ColumnSchema $columnSchema
    ) {
        /* @var $columnData ColumnDataInterface */
        $columnData = $this->columnDataFactory->createColumnData(
            $schemaId,
            $tableId,
            $columnId,
            $columnSchema
        );

        $table->addColumn($columnSchema, $columnData);
    }

    public function modifyColumnOnTable(
        $schemaId,
        $tableId,
        $columnId,
        TableInterface $table,
        ColumnSchema $columnSchema
    ) {
        /* @var $columnData ColumnDataInterface */
        $columnData = $this->columnDataFactory->createColumnData(
            $schemaId,
            $tableId,
            $columnId,
            $columnSchema
        );

        $table->modifyColumn($columnSchema, $columnData);
    }

}
