<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Column;

use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Filesystem\FileInterface;
use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\Table\TableSchemaInterface;
use Addiks\PHPSQL\Column\ColumnData;
use Addiks\PHPSQL\Column\ColumnSchema;

class ColumnDataFactory implements ColumnDataFactoryInterface
{

    public function __construct(
        FilesystemInterface $filesystem
    ) {
        $this->filesystem = $filesystem;
    }

    protected $filesystem;

    /**
     * Creates a new column-data-object.
     *
     * @param  string     $columnId
     * @return ColumnData
     */
    public function createColumnData(
        $schemaId,
        $tableId,
        $columnId,
        ColumnSchema $columnSchema
    ) {
        assert(is_numeric($tableId));

        $columnDataFilePath = sprintf(
            FilePathes::FILEPATH_COLUMN_DATA_FILE,
            $schemaId,
            $tableId,
            $columnId,
            0
        );

        /* @var $columnDataFile FileInterface */
        $columnDataFile = $this->filesystem->getFile($columnDataFilePath);

        /* @var $columnData ColumnData */
        $columnData = new ColumnData($columnDataFile, $columnSchema);

        return $columnData;
    }

}
