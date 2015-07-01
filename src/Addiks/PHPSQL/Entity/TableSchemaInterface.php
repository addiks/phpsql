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

namespace Addiks\PHPSQL\Entity;

use Addiks\PHPSQL\Entity\Page\Schema\Index;

use Addiks\PHPSQL\Entity\Page\Column;

interface TableSchemaInterface
{
    
    public function setDatabaseSchema(SchemaInterface $schema);
    public function getDatabaseSchema();
    
    public function getIndexIterator();
    public function indexExist($name);
    public function getIndexIdByColumns($columnIds);
    public function addIndexPage(Index $indexPage);
    public function getIndexPage($index);
    public function getLastIndex();
    
    public function addColumnPage(Column $column);
    public function getColumnIterator();
    public function getColumnIndex($columnName);
    public function getCachedColumnIds();
    public function dropColumnCache();
    public function getPrimaryKeyColumns();
    public function listColumns();
    public function getColumn($index);
    public function columnExist($columnName);
    public function writeColumn($index = null, Column $column);
}
