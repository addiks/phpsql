<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL\Schema;

class SchemaManager
{
    
    const DATABASE_ID_DEFAULT = "default";
    const DATABASE_ID_META_MYSQL = "mysql";
    const DATABASE_ID_META_INFORMATION_SCHEMA = "information_schema";
    const DATABASE_ID_META_PERFORMANCE_SCHEMA = "performance_schema";
    const DATABASE_ID_META_INDICES = "indicies";
    
    /**
     * Gets the schema for a database.
     * The schema contains information about what tables/views/... are present.
     *
     * @param string $schemaId
     * @throws ErrorException
     * @return Schema
     */
    public function getSchema($schemaId = null)
    {
        
        if (is_null($schemaId)) {
            $schemaId = $this->getCurrentlyUsedDatabaseId();
        }
        
        if (!$this->schemaExists(self::DATABASE_ID_DEFAULT)) {
            $this->createSchema(self::DATABASE_ID_DEFAULT);
        }
        
        $pattern = Internal::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        switch($schemaId){
            case self::DATABASE_ID_META_INDICES:
                /* @var $schema Indicies */
                $this->factorize($schema);
                break;
                
            case self::DATABASE_ID_META_INFORMATION_SCHEMA:
                /* @var $schema InformationSchema */
                $this->factorize($schema);
                break;
                
            default:
                /* @var $schema Schema */
                $this->factorize($schema, [$this->getDatabaseSchemaStorage($schemaId)]);
                break;
                
        }
        
        return $schema;
    }
    
    public function isMetaSchema($schemaId)
    {
        return in_array($schemaId, [
            self::DATABASE_ID_META_INDICES,
            self::DATABASE_ID_META_INFORMATION_SCHEMA,
            self::DATABASE_ID_META_MYSQL,
            self::DATABASE_ID_META_PERFORMANCE_SCHEMA,
        ]);
    }
    
    public function schemaExists($schemaId)
    {
        
        $pattern = Internal::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->isMetaSchema($schemaId)) {
            return true;
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        return $storages->storageExists("Databases/{$schemaId}.schema");
    }
    
    public function createSchema($schemaId)
    {
        
        $pattern = Internal::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->schemaExists($schemaId)) {
            throw new ErrorException("Database '{$schemaId}' already exist!");
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        /* @var $schema Schema */
        $this->factorize($schema, [$this->getDatabaseSchemaStorage($schemaId)]);
        
        $schema->setId($schemaId);
    }
    
    public function removeSchema($schemaId)
    {
        
        $pattern = Internal::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->isMetaSchema($schemaId)) {
            throw new ErrorException("Cannot remove or modify meta-database '{$schemaId}'!");
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $storages->removeStorage($this->getDatabaseSchemaStorage($schemaId));
    }
    
    public function listSchemas()
    {
        
        if (!$this->schemaExists(self::DATABASE_ID_DEFAULT)) {
            $this->createSchema(self::DATABASE_ID_DEFAULT);
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $iterator = $storages->getStoreIterator("Databases");
        
        if (is_null($iterator)) {
            return array();
        }
        
        $result = array();
        foreach ($iterator as $schemaName => $schemaIterator) {
            /* @var $schemaIterator CustomIterator */
            
            if (substr($schemaName, strlen($schemaName)-7)==='.schema') {
                $schemaName = substr($schemaName, 0, strlen($schemaName)-7);
            }
            
            $result[] = (string)$schemaName;
        }
        
        $result[] = self::DATABASE_ID_META_INDICES;
        $result[] = self::DATABASE_ID_META_INFORMATION_SCHEMA;
    #	$result[] = self::DATABASE_ID_META_PERFORMANCE_SCHEMA;
    #	$result[] = self::DATABASE_ID_META_MYSQL;
        
        $result = array_unique($result);
        
        return $result;
    }
    
    public function getTableSchema($tableName, $schemaId = null)
    {
        
        if (is_null($schemaId)) {
            $schemaId = $this->getCurrentlyUsedDatabaseId();
        }
        
        $schema = $this->getSchema($schemaId);
        
        if (is_int($tableName)) {
            $tableIndex = $tableName;
            
        } else {
            $tableIndex = $schema->getTableIndex((string)$tableName);
        }
        
        if (is_null($tableIndex)) {
            return null;
        }
        
        $tableSchemaStorage = $this->getTableSchemaStorage((string)$tableName, $schemaId);
        $indexSchemaStorage = $this->getTableIndexStorage((string)$tableName, $schemaId);

        switch($schemaId){
            
            case self::DATABASE_ID_META_INFORMATION_SCHEMA:
                /* @var $tableSchema InformationSchema */
                $this->factorize($tableSchema, [$tableSchemaStorage, $indexSchemaStorage, $tableName]);
                break;
            
            default:
                /* @var $tableSchema TableSchema */
                $this->factorize($tableSchema, [$tableSchemaStorage, $indexSchemaStorage]);
                break;
        }
        
        $tableSchema->setDatabaseSchema($schema);
        
        return $tableSchema;
    }
    
    public function dropTable($tableName, $schemaId = null)
    {
        
        if (is_null($schemaId)) {
            $schemaId = $this->getCurrentlyUsedDatabaseId();
        }
        
        /* @var $schema Schema */
        $schema = $this->getSchema($schemaId);
        
        if (!$schema->tableExists($tableName)) {
            throw new ErrorException("Table {$tableName} does not exist!");
        }
        
        $schema->unregisterTable($tableName);
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $storagePath = $this->getTableStoragePath($tableName, $schemaId);
        
        $storages->removeStorage($storagePath);
    }
    
    ### VIEW
    
    public function getViewQuery($viewName, Schema $schema = null)
    {
        
        if (is_null($schema)) {
            $schema = $this->getSchema();
        }
        
        $viewIndex = $schema->getViewIndex($viewName);
        
        if (is_null($viewIndex)) {
            return null;
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $viewQueryStorage = $this->getViewDefinitionStorage($viewIndex, $schema->getId());
        
        return $viewQueryStorage->getData();
    }
    
    public function setViewQuery($query, $viewName, Schema $schema = null)
    {
        
        if (is_null($schema)) {
            $schema = $this->getSchema();
        }
        
        $viewIndex = $schema->getViewIndex($viewName);
        
        if (is_null($viewIndex)) {
            $schema->registerView($viewName);
            $viewIndex = $schema->getViewIndex($viewName);
        }
        
        /* @var $storages \Addiks\PHPSQL\Storages */
        $this->factorize($storages);
        
        $viewQueryStorage = $this->getViewDefinitionStorage($viewIndex, $schema->getId());
        $viewQueryStorage->setData($query);
    }

}
