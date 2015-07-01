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

namespace Addiks\Database\Service\SqlParser;

use Addiks\Database\Service\SqlParser\Part\FunctionParser;

use Addiks\Database\Service\SqlParser\Part\ValueParser;

use Addiks\Database\Entity\Job\Statement\UpdateStatement;

use Addiks\Analyser\Tool\TokenIterator;
use Addiks\Database\Entity\Exception\MalformedSql;
use Addiks\Database\Value\Enum\Sql\SqlToken;

use Addiks\Database\Tool\SQLTokenIterator;

use Addiks\Database\Service\SqlParser;

class UpdateSqlParser extends SqlParser{
	
	public function canParseTokens(SQLTokenIterator $tokens){
		return is_int($tokens->isTokenNum(SqlToken::T_UPDATE(), TokenIterator::CURRENT))
		    || is_int($tokens->isTokenNum(SqlToken::T_UPDATE(), TokenIterator::NEXT));
	}
	
	public function convertSqlToJob(SQLTokenIterator $tokens){
		
		$tokens->seekTokenNum(SqlToken::T_UPDATE());
		
		if($tokens->getCurrentTokenNumber() !== SqlToken::T_UPDATE()){
			throw new Error("Tried to parse update statement when token-iterator does not point to T_UPDATE!");
		}
		
		/* @var $dataChange DataChange */
		$this->factorize($dataChange);
		
		/* @var $tableParser TableParser */
		$this->factorize($tableParser);
		
		/* @var $valueParser ValueParser */
		$this->factorize($valueParser);
		
		/* @var $columnParser ColumnParser */
		$this->factorize($columnParser);
		
		/* @var $functionParser FunctionParser */
		$this->factorize($functionParser);
		
		/* @var $updateJob UpdateStatement */
		$this->factorize($updateJob);
		
		if($tokens->seekTokenNum(SqlToken::T_LOW_PRIORITY())){
			$updateJob->setIsLowPriority(true);
		}
		
		if($tokens->seekTokenNum(SqlToken::T_IGNORE())){
			$updateJob->setDoIgnoreErrors(true);
		}
		
		do{
			if(!$tableParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing table specifier in UPDATE statement!", $tokens);
			}
			$updateJob->addTable($tableParser->convertSqlToJob($tokens));
		}while($tokens->seekTokenText(','));
		
		if(!$tokens->seekTokenNum(SqlToken::T_SET())){
			throw new MalformedSql("Missing SET after table specifier in UPDATE statement!", $tokens);
		}
		
		do{
			if(!$columnParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing column specifier for SET part in UPDATE statement!", $tokens);
			}
			$dataChange->setColumn($columnParser->convertSqlToJob($tokens));
			
			if(!$tokens->seekTokenText('=')){
				throw new MalformedSql("Missing '=' on SET part in UPDATE statement!", $tokens);
			}
			
			if(!$valueParser->canParseTokens($tokens)){
				throw new MalformedSql("MIssing valid value on SET part in UPDATE statement!", $tokens);
			}
			
			$dataChange->setValue($valueParser->convertSqlToJob($tokens));
			
			$updateJob->addDataChange(clone $dataChange);
		}while($tokens->seekTokenText(','));
		
		if($tokens->seekTokenNum(SqlToken::T_WHERE())){
				
			if(!$valueParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing condition for WHERE clause in UPDATE statement!", $tokens);
			}
				
			$updateJob->setCondition($valueParser->convertSqlToJob($tokens));
		}
		
		
		if($tokens->seekTokenNum(SqlToken::T_ORDER())){
			if(!$tokens->seekTokenNum(SqlToken::T_BY())){
				throw new MalformedSql("Missing BY after ORDER on UPDATE statement!", $tokens);
			}
			if(!$columnParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing column specifier for ORDER BY part on UPDATE statement!", $tokens);
			}
			$updateJob->setOrderColumn($columnParser->convertSqlToJob($tokens));
			
			if($tokens->seekTokenNum(SqlToken::T_DESC())){
				$updateJob->setOrderDirection(SqlToken::T_DESC());
				
			}elseif($tokens->seekTokenNum(SqlToken::T_ASC())){
				$updateJob->setOrderDirection(SqlToken::T_ASC());
			}
		}
		
		if($tokens->seekTokenNum(SqlToken::T_LIMIT())){
			if(!$tokens->seekTokenNum(T_NUM_STRING)){
				throw new MalformedSql("Missing offset number for LIMIT part in UPDATE statement!", $tokens);
			}
			$updateJob->setLimitOffset((int)$tokens->getCurrentTokenString());
			if($tokens->seekTokenText(',')){
				if(!$tokens->seekTokenNum(T_NUM_STRING)){
					throw new MalformedSql("Missing length number for LIMIT part in UPDATE statement!", $tokens);
				}
				$updateJob->setLimitRowCount((int)$tokens->getCurrentTokenString());
			}
		}
		
		return $updateJob;
	}
}