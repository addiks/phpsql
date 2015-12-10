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

use Addiks\PHPSQL\Entity\Page\SchemaPage as SchemaPage;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;

interface DatabaseSchemaInterface
{

    ### TABLES

    public function listTables();

    public function tableExists($tableName);

    public function getTableIndex($tableName);

    public function registerTable($tableName);

    public function registerTableSchema(DatabaseSchemaPage $schemaPage);

    public function unregisterTable($tableName);

    public function getTablePage($tableId);

    public function createTableSchema(
        $tableSchemaFile,
        $indexSchemaFile,
        $tableName
    );

    ### VIEWS

    public function listViews();

    public function viewExists($viewName);

    public function getViewIndex($viewName);

    public function registerView($viewName);

    public function unregisterView($viewName);
}
