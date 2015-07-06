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

namespace Addiks\PHPSQL\DatabaseAdapter;

use ErrorException;
use Addiks\PHPSQL\Entity\TableSchema;
use Addiks\PHPSQL\Entity\Schema;
use Addiks\PHPSQL\Value\Database\Dsn\Internal;
use Addiks\PHPSQL\Executor;
use Addiks\PHPSQL\SQLTokenIterator;
use Addiks\PHPSQL\ValueResolver;
use Addiks\PHPSQL\SqlParser;
use Addiks\PHPSQL\Entity\Result\TemporaryResult;
use Addiks\PHPSQL\Entity\Job\StatementJob;
use Addiks\PHPSQL\Value\Text\Annotation;
use Addiks\PHPSQL\Database\AbstractDatabase;
use Addiks\PHPSQL\Schema\SchemaManager;
use Addiks\PHPSQL\Filesystem\RealFilesystem;
use Addiks\PHPSQL\SqlParser\Part\ParenthesisParser;
use Addiks\PHPSQL\SqlParser\SelectSqlParser;
use Addiks\PHPSQL\SqlParser\InsertSqlParser;
use Addiks\PHPSQL\SqlParser\UpdateSqlParser;
use Addiks\PHPSQL\SqlParser\DeleteSqlParser;
use Addiks\PHPSQL\SqlParser\ShowSqlParser;
use Addiks\PHPSQL\SqlParser\UseSqlParser;
use Addiks\PHPSQL\SqlParser\CreateSqlParser;
use Addiks\PHPSQL\SqlParser\AlterSqlParser;
use Addiks\PHPSQL\SqlParser\DropSqlParser;
use Addiks\PHPSQL\SqlParser\SetSqlParser;
use Addiks\PHPSQL\SqlParser\DescribeSqlParser;

class InternalDatabaseAdapter implements DatabaseAdapterInterface
{

    public function __construct()
    {
        $this->schemaManager = new SchemaManager();
        $this->filesystem = new RealFilesystem();
        $this->valueResolver = new ValueResolver();

        $this->sqlParser = new SqlParser();
        $this->sqlParser->addSqlParser(new ParenthesisParser());
        $this->sqlParser->addSqlParser(new SelectSqlParser());
        $this->sqlParser->addSqlParser(new InsertSqlParser());
        $this->sqlParser->addSqlParser(new UpdateSqlParser());
        $this->sqlParser->addSqlParser(new DeleteSqlParser());
        $this->sqlParser->addSqlParser(new ShowSqlParser());
        $this->sqlParser->addSqlParser(new UseSqlParser());
        $this->sqlParser->addSqlParser(new CreateSqlParser());
        $this->sqlParser->addSqlParser(new AlterSqlParser());
        $this->sqlParser->addSqlParser(new DropSqlParser());
        $this->sqlParser->addSqlParser(new SetSqlParser());
        $this->sqlParser->addSqlParser(new DescribeSqlParser());
#        $this->sqlParser->addSqlParser(new BeginSqlParser());
#        $this->sqlParser->addSqlParser(new EndSqlParser());
    }

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var SchemaManager
     */
    protected $schemaManager;

    /**
     * @var SqlParser
     */
    protected $sqlParser;

    /**
     * @var ValueResolver
     */
    protected $valueResolver;

    /**
     * @var array
     */
    protected $executors = array();

    public function getTypeName()
    {
        return 'internal';
    }
    
    private $currentDatabaseId = SchemaManager::DATABASE_ID_DEFAULT;
    
    public function getCurrentlyUsedDatabaseId()
    {
        return $this->currentDatabaseId;
    }
    
    public function setCurrentlyUsedDatabaseId($schemaId)
    {
        
        $pattern = Internal::PATTERN;
        if (!preg_match("/{$pattern}/is", $schemaId)) {
            throw new InvalidArgument("Invalid database-id '{$schemaId}' given! (Does not match pattern '{$pattern}')");
        }
        
        if (!$this->schemaExists($schemaId)) {
            throw new Conflict("Database '{$schemaId}' does not exist!");
        }
        
        $this->currentDatabaseId = $schemaId;
        
        return true;
    }
    
    public function query($statementString, array $parameters = array(), SQLTokenIterator $tokens = null)
    {

    #	$this->log($statementString, $parameters);
        
        if ($this->getIsStatementLogActive()) {
            $this->logQuery($statementString);
        }
        
        $result = new TemporaryResult();
            
        try {
            $this->valueResolver->setStatementParameters($parameters);
            
            if (is_null($tokens)) {
                $tokens = new SQLTokenIterator($statementString);
            }
            
            $jobs = $this->sqlParser->convertSqlToJob($tokens);
            
            foreach ($jobs as $statement) {
                /* @var $statement Statement */
                
                $result = $this->queryStatement($statement, $parameters);
            }
            
        } catch (Conflict $exception) {
            print($exception);
                
            throw $exception;
            
        } catch (MalformedSql $exception) {
            print($exception);
                
            throw $exception;
        }
        
        return $result;
    }
    
    public function queryStatement(Statement $statement, array $parameters = array())
    {
        
        if ($this->getIsStatementLogActive()) {
            $this->logStatement($statement);
        }
        
        $executorClass = $statement->getExecutorClass();
        
        if (!isset($this->executors[$executorClass])) {
            $this->executors[$executorClass] = new $executorClass(
                $this->filesystem,
                $this->schemaManager,
                $this->valueResolver
            );
        }

        $result = $this->executors[$executorClass]->executeJob($statement, $parameters);
        
        return $result;
    }
    
    ### LOGGING

    private $isStatementLogActive = false;
    
    public function setIsStatementLogActive($bool)
    {
        $this->isStatementLogActive = (bool)$bool;
    }
    
    public function getIsStatementLogActive()
    {
        return $this->isStatementLogActive;
    }

    protected function logQuery($statement)
    {
    
        $logStorage = $this->getStorage("QueryLog");
    
        $date = date("Y-m-d H-i-s", time());
        
        fwrite($logStorage->getHandle(), "\n\n{$date}:\n" . $statement);
    }
    
    protected function logStatement(Statement $statement)
    {
        
        $logStorage = $this->getStorage("StatementLog");

        $date = date("Y-m-d H-i-s", time());
        
        fwrite($logStorage->getHandle(), "\n\n{$date}:\n" . (string)$statement);
    }
}
