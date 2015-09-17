<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */

namespace Addiks\PHPSQL\Table;

use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Value\Specifier\DatabaseSpecifier;
use Addiks\PHPSQL\Index;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Entity\Page\SchemaPage\IndexPage;
use Addiks\PHPSQL\Table\TableInterface;
use Addiks\PHPSQL\Table\Table;
use Addiks\PHPSQL\Table\Meta\InformationSchema;
use Addiks\PHPSQL\Table\Meta\MySQLTable;
use Addiks\PHPSQL\Table\Meta\InternalIndices;

class TableManager
{
    public function __construct(
        FilesystemInterface $filesystem,
        SchemaManager $schemaManager
    ) {
        $this->filesystem = $filesystem;
        $this->schemaManager = $schemaManager;
    }

    protected $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    protected $schemaManager;

    public function getSchemaManager()
    {
        return $this->schemaManager;
    }

    ### TABLES

    protected $tables = array();

    public function getTable($tableName, $schemaId = null)
    {
        if (is_null($schemaId)) {
            $schemaId = $this->schemaManager->getCurrentlyUsedDatabaseId();
        }
        $tableId = "{$schemaId}.{$tableName}";
        if (!isset($this->tables[$tableId])) {

            /* @var $table TableInterface */
            $table = null;

            switch($schemaId) {
                
                case SchemaManager::DATABASE_ID_META_INDICES:
                    $table = new InternalIndices($tableName, $schemaId);
                    break;
                    
                case SchemaManager::DATABASE_ID_META_MYSQL:
                    $table = new MySQLTable($tableName, $schemaId);
                    break;
                    
                case SchemaManager::DATABASE_ID_META_INFORMATION_SCHEMA:
                    $table = new InformationSchema($tableName, $schemaId);
                    break;
                
                default:
                    $table = new Table(
                        $this->schemaManager,
                        $this->filesystem,
                        $tableName,
                        $schemaId
                    );
                    break;
            }

            $this->tables[$tableId] = $table;
        }
        return $this->tables[$tableId];
    }
}
