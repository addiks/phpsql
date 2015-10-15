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

use Addiks\PHPSQL\Value\Enum\Page\Schema\Type;
use Addiks\PHPSQL\Iterators\CustomIterator;
use Addiks\PHPSQL\Filesystem\FileResourceProxy;
use Addiks\PHPSQL\Database\DatabaseSchemaPage;

/**
 * This entity manages the schema-information about tables, views, ... in the database.
 * Please do NOT use this entity directly, instead use the database resource.
 * @see Database
 *
 * Deleting a table here this will destroy the information about the table in the schema, but not the table-data itself.
 *
 * To cheaply rename tables, the table-data is saved by the integer table-index, not by name.
 */
class DatabaseSchema implements DatabaseSchemaInterface
{
    
    public function __construct(FileResourceProxy $file)
    {
        $this->schemaIndexFile = $file;
    }
    
    private $id;

    public function setId($id)
    {
        $this->id = (string)$id;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * The file containing the schema-index.
     * (Array of Schema-Pages describing views/tables/... )
     *
     * @var FileResourceProxy
     */
    private $schemaIndexFile;
    
    /**
     * @return FileResourceProxy
     */
    protected function getSchemaIndexFile()
    {
        return $this->schemaIndexFile;
    }
    
    public function getSchemaIndexIterator()
    {
        
        $file = $this->getSchemaIndexFile();
        
        $iteratorEntity = new DatabaseSchemaPage();
        
        $skipDeleted = function () use ($file) {
            while (true) {
                $data = $file->read(DatabaseSchemaPage::PAGE_SIZE);
                if (trim($data, "\0")!=='') {
                    $file->seek(0-strlen($data), SEEK_CUR);
                    break;
                }
                if (strlen($data)!==DatabaseSchemaPage::PAGE_SIZE) {
                    break;
                }
            }
        };
        
        return new CustomIterator(null, [
            'valid' => function () use ($file) {
                $data = $file->read(DatabaseSchemaPage::PAGE_SIZE);
                $file->seek(0-strlen($data), SEEK_CUR);
                return strlen($data) === DatabaseSchemaPage::PAGE_SIZE;
            },
            'rewind' => function () use ($file, $skipDeleted) {
                $file->seek(0, SEEK_SET);
                $skipDeleted();
            },
            'key' => function () use ($file) {
                return ($file->tell() / DatabaseSchemaPage::PAGE_SIZE);
            },
            'current' => function () use ($file, $iteratorEntity) {
                $data = $file->read(DatabaseSchemaPage::PAGE_SIZE);
                $file->seek(0-strlen($data), SEEK_CUR);
                $iteratorEntity->setData($data);
                return $iteratorEntity;
            },
            'next' => function () use ($file, $skipDeleted) {
                $file->seek(DatabaseSchemaPage::PAGE_SIZE, SEEK_CUR);
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
        
        $file = $this->getSchemaIndexFile();

        $beforeSeek = $file->tell();
        
        $file->seek($tableId * DatabaseSchemaPage::PAGE_SIZE, SEEK_SET);
        
        $data = $file->read(DatabaseSchemaPage::PAGE_SIZE);
        
        $file->seek($beforeSeek, SEEK_SET);
        
        $entity = new DatabaseSchemaPage();
        $entity->setData($data);
        
        return $entity;
    }
    
    public function registerTable($tableName)
    {
        
        $schemaPage = new DatabaseSchemaPage();
        $schemaPage->setName($name);
        $schemaPage->setType(Type::TABLE());
        
        $indexFile = $this->getSchemaIndexFile();
        $indexFile->addData($schemaPage->getData());
    }
    
    public function registerTableSchema(DatabaseSchemaPage $schemaPage, $index = null)
    {
        
        switch($schemaPage->getType()){
            case Type::TABLE():
                if ($this->tableExists($schemaPage->getName())) {
                    throw new InvalidArgumentException("Table '{$schemaPage->getName()}' already exist!");
                }
                break;
                
            case Type::VIEW():
                if ($this->viewExists($schemaPage->getName())) {
                    throw new InvalidArgumentException("View '{$schemaPage->getName()}' already exist!");
                }
                break;
                
            default:
                throw new ErrorException("Unknown schema-page-type!");
        }
        
        $indexFile = $this->getSchemaIndexFile();
        
        if (is_null($index)) {
            $indexFile->addData($schemaPage->getData());
        } else {
            $indexFile->seek($index * DatabaseSchemaPage::PAGE_SIZE);
            $indexFile->write($schemaPage->getData());
        }
    }
    
    public function unregisterTable($tableName)
    {
        
        if (!$this->tableExists($tableName)) {
            throw new InvalidArgumentException("Table '{$tableName}' does not exist!");
        }
        
        $index = $this->getTableIndex($tableName);
        
        $indexFile = $this->getSchemaIndexFile();
        $indexFile->seek($index * DatabaseSchemaPage::PAGE_SIZE, SEEK_SET);
        $indexFile->write(str_pad("", DatabaseSchemaPage::PAGE_SIZE, "\0"));
        $indexFile->flush();
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
        
        $indexFile = $this->getSchemaIndexFile();
        $indexFile->addData($schemaPage->getData());
    }
    
    public function unregisterView($viewName)
    {
        
        if (!$this->viewExists($viewName)) {
            return;
        }
        
        $index = $this->getViewIndex($viewName);
        
        $indexFile = $this->getSchemaIndexFile();
        $indexFile->seek($index * DatabaseSchemaPage::PAGE_SIZE, SEEK_SET);
        $indexFile->write(str_pad("", "\0", Schema::PAGE_SIZE));
    }
}
