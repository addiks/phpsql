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

namespace Addiks\PHPSQL\Service\RequestHandler;

use Addiks\PHPSQL\Service\ResultWriter;

use Addiks\PHPSQL\Entity\Exception\MalformedSql;

use Addiks\PHPSQL\Entity\Exception\Conflict;

use Addiks\PHPSQL\Entity\Exception\InvalidArgument;

use Addiks\PHPSQL\Entity\Result\ResultInterface;

use Addiks\PHPSQL\Resource\Database;

use Addiks\Workflow\Service\RequestHandler;

/**
 * Executes SQL from the command line
 *
 * @author gerrit
 * @Addiks\Request(path="/system/database")
 */
class SQLConsole extends RequestHandler
{
    
    /**
     * @Addiks\Request(method="EXECUTE")
     */
    public function runExecuteMethod()
    {
        
        /* @var $request \Addiks\Workflow\Request */
        $request = $this->getRequest();
        
        $arguments = $request->getArguments();
        
        if (count($arguments)>0) {
            $statement = array_shift($arguments);
            
            foreach ($arguments as $index => $value) {
                $arguments[$index+1] = $value;
            }
            unset($arguments[0]);
            
            $this->query($statement, $arguments);
            
        } else {
            $this->log(" USAGE: EXECUTE /SQL '[QUERY]'");
        }
    }
    
    /**
     * @Addiks\Request(method="CONSOLE")
     */
    public function runConsoleMethod()
    {
        
        while (true) {
            echo "\n SQL> ";
            
            $statement = trim(fgets(STDIN));
            
            if (in_array($statement, ['quit', 'exit'])) {
                echo "\n bye... \n\n";
                break;
            }
            
            $this->query($statement);
        }
    }
    
    protected function query($statementString, array $parameters = array())
    {
        
        /* @var $database Database */
        $this->factorize($database);
        
        try {
            /* @var $result ResultInterface */
            $result = $database->query($statementString, $parameters);
                
            if (!is_null($result)) {
                echo "\n Result: ".($result->getIsSuccess() ?'SUCCESS' :'FAILURE')."\n\n";
        
                if (isset($_ENV['NORESULTWRITER'])) {
                    foreach ($result as $line) {
                        echo implode(", ", $line)."\n";
                    }
                } else {
                    foreach (new ResultWriter($result) as $line) {
                        echo $line;
                    }
                }
            } else {
                print("\n\n No result!\n\n");
            }
                
        } catch (InvalidArgument $exception) {
            print("\n\n{$exception->getMessage()}\n\n");
            echo $exception->getTraceAsString();
                
        } catch (Conflict $exception) {
            print("\n\n{$exception->getMessage()}\n\n");
            echo $exception->getTraceAsString();
                
        } catch (MalformedSql $exception) {
            print("\n\n{$exception->getMessage()}\n\n");
            echo $exception->getTraceAsString();
        }
    }
}
