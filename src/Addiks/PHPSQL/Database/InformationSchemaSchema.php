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

namespace Addiks\PHPSQL\Database;

use Addiks\PHPSQL\Database\DatabaseSchemaInterface;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Engine;
use Addiks\PHPSQL\Table\TableSchema;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Value\Enum\Page\Schema\Type;

class InformationSchemaSchema implements DatabaseSchemaInterface
{

    public function __construct(
        SchemaManager $schemaManager
    ) {
        $this->schemaManager = $schemaManager;
    }

    protected $schemaManager;

    ### TABLES

    public function listTables()
    {
        $tables = array();

        /* @var $schemaManager SchemaManager */
        $schemaManager = $this->schemaManager;

        $tables[] = "COLUMNS";
        $tables[] = "ENGINES";
        $tables[] = "SCHEMATA";
        $tables[] = "TABLES";
        $tables[] = "VIEWS";

        return $tables;
    }

    public function tableExists($tableName)
    {
        return in_array($tableName, $this->listTables());
    }

    public function getTableIndex($tableName)
    {
        return array_search($tableName, $this->listTables());
    }

    public function registerTable($tableName)
    {
    }

    public function registerTableSchema(DatabaseSchemaPage $schemaPage)
    {
    }

    public function unregisterTable($tableName)
    {
    }

    public function getTablePage($tableId)
    {
        $entity = new DatabaseSchemaPage();
        $entity->setName($tableId);
        $entity->setCollation("latin1_bin");
        $entity->setEngine(Engine::INFORMATION_SCHEMA());
        $entity->setType(Type::TABLE());

        return $entity;
    }

    public function createTableSchema(
        $tableSchemaFile,
        $indexSchemaFile,
        $tableName
    ) {
        $tableSchema = new TableSchema(
            new FileResourceProxy(fopen("php://memory", "w")),
            new FileResourceProxy(fopen("php://memory", "w"))
        );

        switch($tableName){
            case 'TABLES':
                break;
        }

        return $tableSchema;
    }

    ### VIEWS

    public function listViews()
    {
    }

    public function viewExists($viewName)
    {
    }

    public function getViewIndex($viewName)
    {
    }

    public function registerView($viewName)
    {
    }

    public function unregisterView($viewName)
    {
    }
}
