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

namespace Addiks\PHPSQL\Resource;

use Addiks\Common\Resource;

use Addiks\PHPSQL\Entity\Job\Statement\SelectStatement;
use Addiks\Common\Entity;
use Addiks\PHPSQL\Service\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\Service\SqlParser;
use Addiks\PHPSQL\Tool\SQLTokenIterator;
use Addiks\Protocol\Entity\Exception\Error;
use Addiks\PHPSQL\Entity\Job\Statement;
use Addiks\Common\Tool\CustomIterator;
use Addiks\Common\Service\MetaDataCache;
use Addiks\Common\Value\Text\Annotation;

/**
 * A simple object-relational mapper (ORM).
 *
 * Iterating works with SELECT-SQL queries and returnes entities (objects) instead of rows.
 *
 * @see http://en.wikipedia.org/wiki/Object-relational_mapping
 */
class EntityManager extends Resource
{

    /**
     * Executes an SQL query.
     * If possible, results consist of entities instead of array-rows.
     *
     * @param string $statement
     * @param Statement $statementEntity
     * @return \Iterator
     */
    public function query($statement, Statement $statementEntity = null)
    {
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        if (is_null($statementEntity)) {
            /* @var $sqlParser SqlParser */
            $this->factorize($sqlParser);
            
            $statementEntity = $sqlParser->convertSqlToJob(new SQLTokenIterator($statement));
        
        }
        
        $result = $databaseResource->query($statement);
        
        if ($statementEntity instanceof SelectStatement) {
            $tables = $statementEntity->getTables();
            
            if (count($tables)>0) {
                $tableName    = reset($tables);
                $entityClassId  = $this->getClassIdFromTableName($tableName);
                
                if (class_exists($entityClassId)) {
                    $entityManager  = $this;
                    
                    /**
                     * Proxy-function to protected method 'buildEntityFromDataRow'.
                     * @see EntityManager::buildEntityFromDataRow()
                     * @return Entity
                     */
                    $buildEntityFromDataRow = function ($dataRow) use ($entityManager, $entityClassId) {
                        return $entityManager->buildEntityFromDataRow($dataRow, $entityClassId);
                    };
                    
                    $result = new CustomIterator($result, array(
                        'current' => function ($dataRow) use ($buildEntityFromDataRow) {
                            
                            /* @var $entity Entity */
                            $entity = $buildEntityFromDataRow($dataRow);
                            
                            return $entity;
                        },
                    ));
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Stores an entity in the database.
     *
     * @param Entity $entity
     */
    public function persist(Entity $entity)
    {

        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        $dataRow = $this->buildDataRowFromEntity($entity);
        
        $tableName = $this->getTableNameFromClassId(get_class($entity));

        $sets   = array();
        $keys   = array();
        $values = array();
        foreach ($dataRow as $key => $value) {
            if (!is_numeric($value)) {
                $value = "'{$value}'";
            }
            
            $sets[] = "{$key} = {$value}";
            $keys[] = $key;
            $values[] = $value;
        }
        
        $keysPart   = implode(", ", $keys);
        $valuesPart = implode(", ", $values);
        $setPart    = implode(", ", $sets);
        
        $persistStatment = "
			INSERT INTO
				{$tableName}
				({$keysPart})
			VALUES
				({$valuesPart})
			ON DUPLICATE
			UPDATE
				{$tableName}
			SET
				{$setPart}
		";
                
        $databaseResource->query($persistStatment);
    }
    
    /**
     * Removes an entity from the database.
     *
     * @param Entity $entity
     * @return \Addiks\PHPSQL\Entity\Result\Temporary
     */
    public function delete(Entity $entity)
    {

        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        $dataRow = $this->buildDataRowFromEntity($entity);

        $entityClassId = get_class($entity);
        
        $tableName = $this->getTableNameFromClassId($entityClassId);

        $whereParts = array();
        foreach ($this->getPrimaryKeyColumnsByEntityClassId($entityClassId) as $memberName => $columnName) {
            if (isset($dataRow[$columnName])) {
                $value = $dataRow[$columnName];
                if (is_numeric($value)) {
                    $whereParts[] = "{$columnName} = {$dataRow[$columnName]}";
                } else {
                    $whereParts[] = "{$columnName} = '{$dataRow[$columnName]}'";
                }
            }
        }
        
        $wherePart = implode(" AND ", $whereParts);
        
        $deleteStatement = "
			DELETE FROM
				{$tableName}
			WHERE
				{$wherePart}
		";
        
        $result = $databaseResource->query($deleteStatement);
        
        return $result;
    }
    
    /**
     * Loads an entity's members using it primary-key members.
     *
     * Example:
     *  $someEntity = SomeEntity();
     *  $someEntity->setId(12345);
     *  $entityManager->load($someEntity);
     *
     * @param Entity $entity
     */
    public function load(Entity $entity)
    {

        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        $dataRow = $this->buildDataRowFromEntity($entity);
        
        $entityClassId = get_class($entity);
        
        $tableName = $this->getTableNameFromClassId($entityClassId);
        
        $whereParts = array();
        foreach ($this->getPrimaryKeyColumnyByEntityClassId($entityClassId) as $key) {
            $value = $dataRow[$key];
            if (is_numeric($value)) {
                $whereParts[] = "{$key} = {$dataRow[$key]}";
            } else {
                $whereParts[] = "{$key} = '{$dataRow[$key]}'";
            }
        }
        
        $wherePart = implode(" AND ", $whereParts);
        
        $selectStatement = "
			SELECT
				*
			FROM
				{$tableName}
			WHERE
				{$wherePart}
		";

        $result = $databaseResource->query($selectStatement);
        
        $dataRow = reset($result);
        
        $reflectionClass = new \ReflectionClass($entityClassId);
        
        foreach ($dataRow as $key => $value) {
            $reflectionProperty = $reflectionClass->getProperty($key);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($entity, $value);
        }
    }
    
    /**
     * Creates all tables for all known entities in the current database.
     *
     * @throws Error
     */
    public function initializeDatabaseSheme()
    {

        /* @var $metaDataCache MetaDataCache */
        $this->factorize($metaDataCache);

        $createStatements = array();
        $tableDepencies = array();
        
        $primaryKeyMap = array();
        foreach ($metaDataCache->getClassesByAnnotation('Entity') as $entityClassId => $annotations) {
            $primaryKeyMap[$entityClassId] = $this->getPrimaryKeyColumnsByEntityClassId($entityClassId);
        }
        
        foreach ($metaDataCache->getClassesByAnnotation('Entity') as $entityClassId => $annotations) {
            $tableName = $this->getTableNameFromClassId($entityClassId);
            
            $tableDepencies[$entityClassId] = array();
            
            $primaryKeys = $primaryKeyMap[$entityClassId];
            
            $columnsParts = array();
            $foreignKeyParts = array();

            $memberVarTypes = array();
            
            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'var') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */
                    
                    $memberVarTypes[$memberName] = reset($annotation->getTags());
                }
            }

            $generatedMembers = array();
            
            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'GeneratedValue') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */
            
                    $strategy = "IDENTITY";
                    
                    if (isset($annotation['strategy'])) {
                        $strategy = $annotation['strategy'];
                    }
                    
                    $generatedMembers[$memberName] = $strategy;
                }
            }
            
            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'Column') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */
                    
                    $columnName = null;
                    if (isset($annotation['name'])) {
                        $columnName = $annotation['name'];
                         
                    } else {
                        $columnName = $this->getColumnNameByMemberName($memberName);
                    }
                    
                    $dataType = null;
                    if (isset($annotation['type'])) {
                        $dataType = $annotation['type'];
                        
                    } else {
                        $dataType = $memberVarTypes[$memberName];
                    }
                    $dataType = $this->mapPHPTypeToSQLType($dataType);
                    
                    $primaryKeyPart = isset($primaryKeys[$columnName]) ?' PRIMARY KEY' :'';
                    
                    $generatorPart = "";
                    if (isset($generatedMembers[$memberName])) {
                        switch(strtoupper($generatedMembers[$memberName])){
                            
                            case 'SEQUENCE':
                                break;
                                
                            case 'TABLE':
                                break;

                            case 'AUTO':
                            case 'IDENTITY':
                                $generatorPart = " AUTO_INCREMENT";
                                break;
                        }
                    }
                    
                    $lengthPart = "";
                    if (isset($annotation['length'])) {
                        $lengthPart = "({$annotation['length']})";
                        
                    } elseif (in_array($dataType, $neededLengthDataTypes)) {
                        $lengthPart = "(256)";
                    }
                    
                    $columnsParts[] = "{$columnName} {$dataType}{$lengthPart}{$primaryKeyPart}{$generatorPart}";
                }
            }

            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'String') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */

                    $primaryKeyPart = isset($primaryKeys[$columnName]) ?' PRIMARY KEY' :'';
                        
                    $columnName = null;
                    if (isset($annotation['name'])) {
                        $columnName = $annotation['name'];
                            
                    } else {
                        $columnName = $this->getColumnNameByMemberName($memberName);
                    }

                    $generatorPart = "";
                    if (isset($generatedMembers[$memberName])) {
                        switch(strtoupper($generatedMembers[$memberName])){
                                
                            case 'SEQUENCE':
                                break;
                    
                            case 'TABLE':
                                break;
                    
                            case 'AUTO':
                            case 'IDENTITY':
                                $generatorPart = " AUTO_INCREMENT";
                                break;
                        }
                    }
                        
                    $columnsParts[] = "{$columnName} VARCHAR(256){$primaryKeyPart}{$generatorPart}";
                }
            }
            
            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'ManyToOne') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */
                        
                    if (isset($annotation['targetEntity'])) {
                        $targetEntityClassId = $annotation['targetEntity'];
                        
                        $targetTableName = $this->getTableNameFromClassId($targetEntityClassId);
                        
                        $columnName = $this->getColumnNameByMemberName($memberName);
                        
                        $targetColumnName = "";
                        if (isset($annotation['inversedBy'])) {
                            $targetColumnName = "({$this->getColumnNameByMemberName($annotation['inversedBy'])})";
                            
                        } else {
                            $targetPrimaryKey = $primaryKeyMap[$targetEntityClassId];
                            $targetPrimaryKeyString = implode(", ", $targetPrimaryKey);
                            
                            $targetColumnName = "({$targetPrimaryKeyString})";
                        }

                        $onDelete = "";
                        if (isset($annotation['onDelete'])) {
                            $onDelete = " ON DELETE {$annotation['onDelete']}";
                        }
                        
                        $onUpdate = "";
                        if (isset($annotation['onUpdate'])) {
                            $onUpdate = " ON UPDATE {$annotation['onUpdate']}";
                        }

                        $columnsParts[] = "{$columnName} INT";
                        $foreignKeyParts[] = "FOREIGN KEY ({$columnName}) REFERENCES {$targetTableName}{$targetColumnName}{$onDelete}{$onUpdate}";
                        
                        $tableDepencies[$entityClassId][$targetEntityClassId] = $targetEntityClassId;
                    }
                }
            }

            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'Index') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */

                    $columnName = $this->getColumnNameByMemberName($memberName);
                    
                    $uniquePart = "";
                    if (isset($annotation['unique']) && strtolower($annotation['unique']) === 'true') {
                        $uniquePart = "UNIQUE ";
                    }
                    
                    $foreignKeyParts[] = "{$uniquePart}INDEX ({$columnName})";
                }
            }

            foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'UniqueIndex') as $memberName => $annotations) {
                foreach ($annotations as $annotation) {
                    /* @var $annotation Annotation */

                    $foreignKeyParts[] = "UNIQUE INDEX ({$columnName})";
                }
            }
                    
            $columnsPart = implode(",\n\t\t\t\t\t\t", array_merge($columnsParts, $foreignKeyParts));
            
            $createStatements[$entityClassId] = "
				CREATE TABLE {$tableName} (
					{$columnsPart}
				);
			";
        }

        ### EXECUTE CREATE STATEMENTS
        
        /* @var $databaseResource Database */
        $this->factorize($databaseResource);
        
        $createStatement = "";
        
        do {
            $depencyCountOfLastIteration = count($tableDepencies);
        
            foreach ($tableDepencies as $entityClassId => $depencies) {
                if (count($depencies)<=0) {
                    // remove entity from depencies list
                    unset($tableDepencies[$entityClassId]);

                    // remove entity from depencies of other entities
                    foreach ($tableDepencies as $remoteEntityClassId => $remoteDepencies) {
                        if (isset($remoteDepencies[$entityClassId])) {
                            unset($tableDepencies[$remoteEntityClassId][$entityClassId]);
                        }
                    }

                    $createStatement .= $createStatements[$entityClassId];
                }
            }
        } while (count($tableDepencies)>0 && $depencyCountOfLastIteration !== count($tableDepencies));
        
        if (count($tableDepencies) < 0) {
            $databaseResource->query($createStatement);
            
        } else {
            $failedDepenciesString = implode(", ", array_keys($tableDepencies));
            throw new Error("Cannot resolve entity-depencies of these entities: {$failedDepenciesString}!");
        }
    }
    
    ### HELPERS
    
    /**
     * Converts a php-datatype into the regarding sql-datatype.
     *
     * @param string $phpType
     * @return string
     */
    protected function mapPHPTypeToSQLType($phpType)
    {
        
        $phpType = strtolower($phpType);
        
        $sqlType = "NULL";
        
        $typeMap = array(
            'null'    => "NULL",
            'bool'    => "TINYINT",
            'int'     => "INT",
            'integer' => "INT",
            'numeric' => "INT",
            'float'   => "FLOAT",
            'decimal' => "DECIMAL",
            'char'    => "VARCHAR",
            'string'  => "VARCHAR",
        );
        
        if (isset($typeMap[$phpType])) {
            $sqlType = $typeMap[$phpType];
            http://xkcd.com/
        } else {
            // probably object => foreign key
            $sqlType = "INT";
        }
        
        return $sqlType;
    }
    
    /**
     * Creates an key-value array containing all fields used in the table of given entity.
     *
     * @param Entity $entity
     * @return multitype:mixed
     */
    protected function buildDataRowFromEntity(Entity $entity)
    {
        
        $entityClassId = get_class($entityClassId);

        $reflectionClass = new \ReflectionClass($entityClassId);
        
        $dataRow = array();

        foreach ($this->getColumnsByEntityClassId($entityClassId) as $memberName => $columnName) {
            $reflectionProperty = $reflectionClass->getProperty($memberName);
            $reflectionProperty->setAccessible(true);
            
            $dataRow[$columnName] = $reflectionProperty->getValue($entity);
        }
        
        return $dataRow;
    }
    
    /**
     * Creates an entity-object from given key-value-array.
     *
     * @param array $dataRow
     * @param string $entityClassId
     * @return Entity
     */
    protected function buildEntityFromDataRow(array $dataRow, $entityClassId)
    {

        $membersCount = 0;
        $membersPart  = "";
        
        foreach ($this->getColumnsByEntityClassId($entityClassId) as $memberName => $columnName) {
            $membersPart .= serialize($dataRow[$columnName]);
            $membersCount++;
        }
        
        $classIdLength = strlen($entityClassId);
        
        /* @var $entity Entity */
        $entity = unserialize("O:{$classIdLength}:\"{$entityClassId}\":{$membersCount}:{{$membersPart}}");
        
        return $entity;
    }
    
    /**
     * Gets the table-columns to be used for a given entity-class-id.
     *
     * @param string $entityClassId
     * @return multitype:string
     */
    protected function getColumnsByEntityClassId($entityClassId)
    {
        
        /* @var $metaDataCache MetaDataCache */
        $this->factorize($metaDataCache);
        
        $columns = array();
        
        foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'Column') as $memberName => $annotations) {
            foreach ($annotations as $annotation) {
                /* @var $annotation Annotation */
                
                $columnName = null;
                if (isset($annotation['name'])) {
                    $columnName = $annotation['name'];
                    
                } else {
                    $columnName = $memberName;
                    $columnNameParts = preg_split("/(?=[A-Z0-9_])/s", $columnName, -1, PREG_SPLIT_NO_EMPTY);
                    $columnNameParts = array_map("strtolower", $columnNameParts);
                    $columnName = implode("_", $columnNameParts);
                }
                
                $columns[$memberName] = $columnName;
            }
        }
        
        return $columns;
    }
    
    /**
     * Gets the primary-key table-columns to be used for a given entity-class-id.
     *
     * @param string $entityClassId
     * @return multitype:string
     */
    protected function getPrimaryKeyColumnsByEntityClassId($entityClassId)
    {

        /* @var $metaDataCache MetaDataCache */
        $this->factorize($metaDataCache);

        $columns = array();

        foreach ($metaDataCache->getMembersByClassAndAnnotation($entityClassId, 'Id') as $memberName => $annotations) {
            foreach ($annotations as $annotation) {
                /* @var $annotation Annotation */

                $columnName = null;
                if (isset($annotation['name'])) {
                    $columnName = $annotation['name'];
                        
                } else {
                    $columnName = $this->getColumnNameByMemberName($memberName);
                }
                 
                $columns[$memberName] = $columnName;
            }
        }
        
        return $columns;
    }

    /**
     * Converts an entity-member-name into a column-name.
     *
     * @param string $memberName
     * @return string
     */
    protected function getColumnNameByMemberName($memberName)
    {

        $columnName = $memberName;
        $columnNameParts = preg_split("/(?=[A-Z0-9_])/s", $columnName, -1, PREG_SPLIT_NO_EMPTY);
        $columnNameParts = array_map("strtolower", $columnNameParts);
        $columnName = implode("_", $columnNameParts);
        
        return $columnName;
    }
    
    /**
     * Convers a table-name into an entity-class-id.
     *
     * @param string $tableName
     * @return mixed
     */
    protected function getClassIdFromTableName($tableName)
    {
        return str_replace(" ", "\\", ucwords(str_replace("_", " ", $tableName)));
    }
    
    /**
     * Convers an entity-class-id into a table-name.
     *
     * @param string $entityClassId
     * @return mixed
     */
    protected function getTableNameFromClassId($entityClassId)
    {
        return str_replace('\\', '_', strtolower($entityClassId));
    }
}
