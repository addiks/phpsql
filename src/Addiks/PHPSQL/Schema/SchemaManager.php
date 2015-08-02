<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

namespace Addiks\PHPSQL\Schema;

use Addiks\PHPSQL\Schema\Meta\InformationSchema;
use Addiks\PHPSQL\Entity\Schema;
use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Value\Database\Dsn\InternalDsn;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Filesystem\FilePathes;

class SchemaManager
{
    
    const DATABASE_ID_DEFAULT = "default";
    const DATABASE_ID_META_MYSQL = "mysql";
    const DATABASE_ID_META_INFORMATION_SCHEMA = "information_schema";
    const DATABASE_ID_META_PERFORMANCE_SCHEMA = "performance_schema";
    const DATABASE_ID_META_INDICES = "indicies";

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected $filesystem;

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    private $currentDatabaseId = SchemaManager::DATABASE_ID_DEFAULT;
    
    public function getCurrentlyUsedDatabaseId()
    {
        return $this->currentDatabaseId;
    }
    
    public function setCurrentlyUsedDatabaseId($schemaId)
    {
        
        $pattern = InternalDsn::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if (!$this->schemaExists($schemaId)) {
            throw new Conflict("Database '{$schemaId}' does not exist!");
        }
        
        $this->currentDatabaseId = $schemaId;
        
        return true;
    }
    
    protected $schemas = array();

    /**
     * Gets the schema for a database.
     * The schema contains information about existing tables/views/etc.
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
        
        $pattern = InternalDsn::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if (!isset($this->schemas[$schemaId])) {
            switch($schemaId){
                case self::DATABASE_ID_META_INDICES:
                    $this->schemas[$schemaId] = new Indicies($this);
                    break;
                    
                case self::DATABASE_ID_META_INFORMATION_SCHEMA:
                    $this->schemas[$schemaId] = new InformationSchema($this);
                    break;
                    
                default:
                    $schemaFilePath = sprintf(FilePathes::FILEPATH_SCHEMA, $schemaId);
                    $schemaFile = $this->filesystem->getFile($schemaFilePath);
                    $this->schemas[$schemaId] = new Schema($schemaFile);
                    break;
                    
            }
        }
        return $this->schemas[$schemaId];
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
        
        $pattern = InternalDsn::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->isMetaSchema($schemaId)) {
            return true;
        }
        
        return $this->filesystem->fileExists(sprintf(FilePathes::FILEPATH_SCHEMA, $schemaId));
    }
    
    public function createSchema($schemaId)
    {
        
        $pattern = InternalDsn::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->schemaExists($schemaId)) {
            throw new ErrorException("Database '{$schemaId}' already exist!");
        }
        
        $schemaFilePath = sprintf(FilePathes::FILEPATH_SCHEMA, $schemaId);
        $schemaFile = $this->filesystem->getFile($schemaFilePath);

        /* @var $schema Schema */
        $schema = new Schema($schemaFile);
        $schema->setId($schemaId);

        return $schema;
    }
    
    public function removeSchema($schemaId)
    {
        
        $pattern = InternalDsn::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new ErrorException("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if ($this->isMetaSchema($schemaId)) {
            throw new ErrorException("Cannot remove or modify meta-database '{$schemaId}'!");
        }
        
        $this->filesystem->fileUnlink($this->getSchemaFile($schemaId));
    }
    
    public function listSchemas()
    {
        
        if (!$this->schemaExists(self::DATABASE_ID_DEFAULT)) {
            $this->createSchema(self::DATABASE_ID_DEFAULT);
        }
        
        /* @var $filesystem FilesystemInterface */
        $filesystem = $this->filesystem;

        list($schemaPath, $suffix) = explode("%s", FilePathes::FILEPATH_SCHEMA);

        foreach ($filesystem->getDirectoryIterator($filesystem) as $item) {
            /* @var $item DirectoryIterator */

            $filename = $item->getFilename();
            if (substr($filename, strlen($filename)-strlen($suffix)) === $suffix) {
                $result[] = substr($filename, 0, strlen($filename)-strlen($suffix));
            }
        }

        $result[] = self::DATABASE_ID_META_INDICES;
        $result[] = self::DATABASE_ID_META_INFORMATION_SCHEMA;
    #	$result[] = self::DATABASE_ID_META_PERFORMANCE_SCHEMA;
    #	$result[] = self::DATABASE_ID_META_MYSQL;
        
        $result = array_unique($result);
        
        return $result;
    }

    protected $tableSchemas = array();
    
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
        
        if (!isset($this->tableSchemas["{$schemaId}.{$tableName}"])) {
            $tableSchemaFilepath = sprintf(FilePathes::FILEPATH_TABLE_SCHEMA, $schemaId, $tableName);
            $indexSchemaFilepath = sprintf(FilePathes::FILEPATH_TABLE_INDEX_SCHEMA, $schemaId, $tableName);

            $tableSchemaFile = $this->filesystem->getFile($tableSchemaFilepath);
            $indexSchemaFile = $this->filesystem->getFile($indexSchemaFilepath);

            switch($schemaId){
                
                case self::DATABASE_ID_META_INFORMATION_SCHEMA:
                    $this->tableSchemas["{$schemaId}.{$tableName}"] = new InformationSchema(
                        $tableSchemaFile,
                        $indexSchemaFile,
                        $tableName
                    );
                    break;
                
                default:
                    $this->tableSchemas["{$schemaId}.{$tableName}"] = new TableSchema(
                        $tableSchemaFile,
                        $indexSchemaFile
                    );
                    break;
            }
        }
        
        $this->tableSchemas["{$schemaId}.{$tableName}"]->setDatabaseSchema($schema);
        
        return $this->tableSchemas["{$schemaId}.{$tableName}"];
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

        $viewFilePath = sprintf(FilePathes::FILEPATH_VIEW_SQL, $schema->getId(), $viewIndex);
        return $this->filesystem->getFileContents($viewFilePath);
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

        $viewFilePath = sprintf(FilePathes::FILEPATH_VIEW_SQL, $schema->getId(), $viewIndex);
        $this->filesystem->putFileContents($viewFilePath, $query);
    }
}
