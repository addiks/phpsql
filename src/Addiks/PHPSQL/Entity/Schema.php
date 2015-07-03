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

use Addiks\PHPSQL\Value\Enum\Page\Schema\Type;

use Addiks\PHPSQL\Entity\Storage;
use Addiks\PHPSQL\Entity\Page\Schema as SchemaPage;

use Addiks\PHPSQL\Entity;
use Addiks\PHPSQL\Tool\CustomIterator;

/**
 * This entity manages the schema-information about tables, views, ... in the database.
 * Please do NOT use this entity directly, instead use the database resource.
 * @see Database
 *
 * Deleting a table here this will destroy the information about the table in the schema, but not the table-data itself.
 *
 * To cheaply rename tables, the table-data is saved by the integer table-index, not by name.
 */
class Schema extends Entity implements SchemaInterface
{
    
    public function __construct(Storage $index)
    {
        
        $this->schemaIndexStorage = $index;
    }
    
    /**
     * The storage containing the schema-index.
     * (Array of Schema-Pages describing views/tables/... )
     *
     * @var Storage
     */
    private $schemaIndexStorage;
    
    /**
     * @return Storage
     */
    protected function getSchemaIndexStorage()
    {
        return $this->schemaIndexStorage;
    }
    
    public function getSchemaIndexIterator()
    {
        
        $storage = $this->getSchemaIndexStorage();
        
        $iteratorEntity = new SchemaPage();
        
        $skipDeleted = function () use ($storage) {
            while (true) {
                if (trim($data = fread($storage->getHandle(), SchemaPage::PAGE_SIZE), "\0")!=='') {
                    fseek($storage->getHandle(), 0-strlen($data), SEEK_CUR);
                    break;
                }
                if (strlen($data)!==SchemaPage::PAGE_SIZE) {
                    break;
                }
            }
        };
        
        return new CustomIterator(null, [
            'valid' => function () use ($storage) {
                $data = fread($storage->getHandle(), SchemaPage::PAGE_SIZE);
                fseek($storage->getHandle(), 0-strlen($data), SEEK_CUR);
                return strlen($data) === SchemaPage::PAGE_SIZE;
            },
            'rewind' => function () use ($storage, $skipDeleted) {
                fseek($storage->getHandle(), 0, SEEK_SET);
                $skipDeleted();
            },
            'key' => function () use ($storage) {
                return (ftell($storage->getHandle()) / SchemaPage::PAGE_SIZE);
            },
            'current' => function () use ($storage, $iteratorEntity) {
                $data = fread($storage->getHandle(), SchemaPage::PAGE_SIZE);
                fseek($storage->getHandle(), 0-strlen($data), SEEK_CUR);
                $iteratorEntity->setData($data);
                return $iteratorEntity;
            },
            'next' => function () use ($storage, $skipDeleted) {
                fseek($storage->getHandle(), SchemaPage::PAGE_SIZE, SEEK_CUR);
                $skipDeleted();
            }
        ]);
    }
    
    ### TABLES
    
    public function listTables()
    {
        
        $tables = array();
        foreach ($this->getSchemaIndexIterator() as $index => $schemaPage) {
            /* @var $schemaPage Schema */
            
            if ($schemaPage->getType() === Type::TABLE()) {
                $tables[$index] = $schemaPage->getName();
            }
        }
        
        return $tables;
    }
    
    public function tableExists($tableName)
    {
        
        return !is_null($this->getTableIndex($tableName));
    }
    
    public function getTableIndex($tableName)
    {
        
        foreach ($this->getSchemaIndexIterator() as $index => $schemaPage) {
            /* @var $schemaPage Schema */
                
            if ($schemaPage->getType() !== Type::TABLE()) {
                continue;
            }
                
            if ($schemaPage->getName() === $tableName) {
                return $index;
            }
        }
    }
    
    public function getTablePage($tableId)
    {
        
        if (!is_numeric($tableId)) {
            $tableId = $this->getTableIndex($tableId);
        }
        
        $handle = $this->getSchemaIndexStorage()->getHandle();
        
        $beforeSeek = ftell($handle);
        
        fseek($handle, $tableId * SchemaPage::PAGE_SIZE, SEEK_SET);
        
        $data = fread($handle, SchemaPage::PAGE_SIZE);
        
        fseek($handle, $beforeSeek, SEEK_SET);
        
        $entity = new SchemaPage();
        $entity->setData($data);
        
        return $entity;
    }
    
    public function registerTable($tableName)
    {
        
        $schemaPage = new SchemaPage();
        $schemaPage->setName($name);
        $schemaPage->setType(Type::TABLE());
        
        $indexStorage = $this->getSchemaIndexStorage();
        $indexStorage->addData($schemaPage->getData());
    }
    
    public function registerTableSchema(SchemaPage $schemaPage)
    {
        
        switch($schemaPage->getType()){
            case Type::TABLE():
                if ($this->tableExists($schemaPage->getName())) {
                    throw new Conflict("Table '{$schemaPage->getName()}' already exist!");
                }
                break;
                
            case Type::VIEW():
                if ($this->viewExists($schemaPage->getName())) {
                    throw new Conflict("View '{$schemaPage->getName()}' already exist!");
                }
                break;
                
            default:
                throw new ErrorException("Unknown schema-page-type!");
        }
        
        $indexStorage = $this->getSchemaIndexStorage();
        $indexStorage->addData($schemaPage->getData());
    }
    
    public function unregisterTable($tableName)
    {
        
        if (!$this->tableExists($tableName)) {
            throw new Conflict("Table '{$tableName}' does not exist!");
        }
        
        $index = $this->getTableIndex($tableName);
        
        $indexStorage = $this->getSchemaIndexStorage();
        fseek($indexStorage->getHandle(), $index * SchemaPage::PAGE_SIZE, SEEK_SET);
        
        fwrite($indexStorage->getHandle(), str_pad("", SchemaPage::PAGE_SIZE, "\0"));
        
        fflush($indexStorage->getHandle());
    }
    
    ### VIEWS
    
    public function listViews()
    {
        
        $views = array();
        foreach ($this->getSchemaIndexIterator() as $index => $schemaPage) {
            /* @var $schemaPage Schema */
                
            if ($schemaPage->getType() === Type::VIEW()) {
                $views[$index] = $schemaPage->getName();
            }
        }
        
        return $views;
    }
    
    public function viewExists($viewName)
    {
        return !is_null($this->getViewIndex());
    }
    
    public function getViewIndex($viewName)
    {
    
        foreach ($this->getSchemaIndexIterator() as $index => $schemaPage) {
            /* @var $schemaPage Schema */
    
            if ($schemaPage->getType() !== Type::VIEW()) {
                continue;
            }
    
            if ($schemaPage->getName() === $tableName) {
                return $index;
            }
        }
    }
    
    public function registerView($viewName)
    {
        
        $schemaPage = new Schema();
        $schemaPage->setName($name);
        $schemaPage->setType(Type::VIEW());
        
        $indexStorage = $this->getSchemaIndexStorage();
        $indexStorage->addData($schemaPage->getData());
    }
    
    public function unregisterView($viewName)
    {
        
        if (!$this->viewExists($viewName)) {
            return;
        }
        
        $index = $this->getViewIndex($viewName);
        
        $indexStorage = $this->getSchemaIndexStorage();
        fseek($indexStorage->getHandle(), $index * SchemaPage::PAGE_SIZE, SEEK_SET);
        fwrite($indexStorage->getHandle(), str_pad("", "\0", Schema::PAGE_SIZE));
    }
}
