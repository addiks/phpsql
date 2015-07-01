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

use Addiks\Database\Entity\Job\Part\ConditionJob;

use Addiks\Database\Service\SqlParser\Part\FunctionParser;

use Addiks\Database\Service\SqlParser\Part\Condition;

use Addiks\Database\Value\Enum\Sql\Select\SpecialFlags;

use Addiks\Database\Service\SqlParser\Part\Specifier\TableParser;

use Addiks\Database\Service\SqlParser\Part\Specifier\ColumnParser;

use Addiks\Database\Service\SqlParser\Part\JoinDefinition;

use Addiks\Analyser\Service\TokenParser\Expression\FunctionCallParser;

use Addiks\Database\Service\SqlParser\Part\Parenthesis;

use Addiks\Database\Service\SqlParser\Part\ValueParser;

use Addiks\Database\Entity\Job\Statement\SelectStatement;

use Addiks\Database\Service\SqlParser;

use Addiks\Database\Entity\Exception\MalformedSql;
use Addiks\Database\Value\Enum\Sql\SqlToken;
use Addiks\Analyser\Tool\TokenIterator;

use Addiks\Database\Tool\SQLTokenIterator;

/**
 * This class converts a tokenized sql-select-statement into an job-entity.
 * @see SQLTokenIterator
 * @see Select
 */
class SelectSqlParser extends SqlParser{
	
	public function canParseTokens(SQLTokenIterator $tokens){
		return is_int($tokens->isTokenNum(SqlToken::T_SELECT(), TokenIterator::CURRENT))
		    || is_int($tokens->isTokenNum(SqlToken::T_SELECT(), TokenIterator::NEXT));
	}
	
	public function convertSqlToJob(SQLTokenIterator $tokens){
		
		// catch both cases when select is current AND when its next token.
		$tokens->seekTokenNum(SqlToken::T_SELECT());
		
		if($tokens->getCurrentTokenNumber() !== SqlToken::T_SELECT()){
			throw new Error("Tried to convert select-sql to job when sql-token-iterator does not point to T_SELECT!");
		}
		
		/* @var $valueParser ValueParser */
		$this->factorize($valueParser);
		
		/* @var $parenthesisParser Parenthesis */
		$this->factorize($parenthesisParser);
		
		/* @var $functionParser FunctionCallParser */
		$this->factorize($functionParser);
		
		/* @var $joinParser JoinDefinition */
		$this->factorize($joinParser);
		
		/* @var $columnParser ColumnParser */
		$this->factorize($columnParser);
		
		/* @var $tableSpecifierParser TableParser */
		$this->factorize($tableSpecifierParser);
		
		/* @var $entitySelect SelectStatement */
		$this->factorize($entitySelect);
		
		### SPECIAL FLAGS
		
		foreach([
			[SpecialFlags::FLAG_ALL()                , SqlToken::T_ALL()],
			[SpecialFlags::FLAG_DISTINCT()           , SqlToken::T_DISTINCT()],
			[SpecialFlags::FLAG_DISTINCTROW()        , SqlToken::T_DISTINCTROW()],
			[SpecialFlags::FLAG_HIGH_PRIORITY()      , SqlToken::T_HIGH_PRIORITY()],
			[SpecialFlags::FLAG_STRAIGHT_JOIN()      , SqlToken::T_STRAIGHT_JOIN()],
			[SpecialFlags::FLAG_SQL_SMALL_RESULT()   , SqlToken::T_SQL_SMALL_RESULT()],
			[SpecialFlags::FLAG_SQL_BIG_RESULT()     , SqlToken::T_SQL_BIG_RESULT()],
			[SpecialFlags::FLAG_SQL_BUFFER_RESULT()  , SqlToken::T_SQL_BUFFER_RESULT()],
			[SpecialFlags::FLAG_SQL_CACHE()          , SqlToken::T_SQL_CACHE()],
			[SpecialFlags::FLAG_SQL_NO_CACHE()       , SqlToken::T_SQL_NO_CACHE()],
			[SpecialFlags::FLAG_SQL_CALC_FOUND_ROWS(), SqlToken::T_SQL_CALC_FOUND_ROWS()],
		] as $pair){
			list($flagValue, $tokenNum) = $pair;
			
			if($tokens->seekTokenNum($tokenNum)){
				$entitySelect->addFlag($flagValue);
			}
		}
		
		### COLLECT COLUMNS
		
		do{
			try{
				switch(true){
					
					# parse jokers like: fooTable.*
					case is_int($tokens->isTokenText('*', TokenIterator::NEXT, [T_STRING, '.'])):
						if($tableSpecifierParser->canParseTokens($tokens)){
							$tableFilter = $tableSpecifierParser->convertSqlToJob($tokens);
						}else{
							$tableFilter = null;
						}
						$tokens->seekTokenText('*', TokenIterator::NEXT, [T_STRING, '.']);
						$entitySelect->addColumnAllTable($tableFilter);
						break;
						
					case $valueParser->canParseTokens($tokens):
						$value = $valueParser->convertSqlToJob($tokens);
						if($tokens->seekTokenNum(T_STRING, TokenIterator::NEXT, [SqlToken::T_AS()])){
							$entitySelect->addColumnValue($value, $tokens->getCurrentTokenString());
						}else{
							$entitySelect->addColumnValue($value);
						}
						break;
						
					default:
						throw new MalformedSql("Non-column-sql found in column-part of select!", $tokens);
				}
			}catch(MalformedSql $exception){
				throw new MalformedSql($exception->getMessage(), $tokens);
			}	
		}while($tokens->seekTokenText(','));
		
		### COLLECT TABLES
		
		/* @var $tableSpecifierParser TableParser */
		$this->factorize($tableSpecifierParser);
		
		/* @var $conditionParser Condition */
		$this->factorize($conditionParser);
		
		if($tokens->seekTokenNum(SqlToken::T_FROM())){
			if(!$joinParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing valid join definition after FROM in SELECT statement!", $tokens);
			}
			$entitySelect->setJoinDefinition($joinParser->convertSqlToJob($tokens));
		}
		
		### PREPENDED CONDITION (WHERE)
		
		if($tokens->seekTokenNum(SqlToken::T_WHERE())){
			
			if(!$valueParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing condition for WHERE clause in SELECT statement!", $tokens);
			}
			
			$entitySelect->setCondition($valueParser->convertSqlToJob($tokens));
		}
		
		### GROUP
		
		if($tokens->seekTokenNum(SqlToken::T_GROUP())){
			if(!$tokens->seekTokenNum(SqlToken::T_BY())){
				throw new MalformedSql("Missing BY after GROUP in SELECT statement!", $tokens);
			}
			do{
				switch(true){
					case $columnParser->canParseTokens($tokens):
						$groupValue = $columnParser->convertSqlToJob($tokens);
						break;
						
					default:
						throw new MalformedSql("Invalid grouping value in SELECT statement!!", $tokens);
				}
				
				if($tokens->seekTokenNum(SqlToken::T_DESC())){
					$entitySelect->addGrouping($groupValue, SqlToken::T_DESC());
					
				}else{
					$tokens->seekTokenNum(SqlToken::T_ASC());
					$entitySelect->addGrouping($groupValue, SqlToken::T_ASC());
				}
				
			}while($tokens->seekTokenText(','));
		}
		
		### APPENDED CONDITION (HAVING)
		
		if($tokens->seekTokenNum(SqlToken::T_HAVING())){
			
			if(!$valueParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing condition for WHERE clause in SELECT statement!", $tokens);
			}
				
			/* @var $condition ConditionJob */
			$this->factorize($condition);
				
			$condition->setFirstParameter($valueParser->convertSqlToJob($tokens));
				
			$entitySelect->setResultFilter($condition);
		}
		
		### ORDER
		
		if($tokens->seekTokenNum(SqlToken::T_ORDER())){
			if(!$tokens->seekTokenNum(SqlToken::T_BY())){
				throw new MalformedSql("Missing BY after ORDER on SELECT statement!", $tokens);
			}
			do{
				if(!$valueParser->canParseTokens($tokens)){
					throw new MalformedSql("Missing value for ORDER BY part on SELECT statement!", $tokens);
				}
				
				$orderValue = $valueParser->convertSqlToJob($tokens);
				if($tokens->seekTokenNum(SqlToken::T_DESC())){
					$entitySelect->addOrderColumn($orderValue, SqlToken::T_DESC());
						
				}else{
					$tokens->seekTokenNum(SqlToken::T_ASC());
					$entitySelect->addOrderColumn($orderValue, SqlToken::T_ASC());
				}
				
			}while($tokens->seekTokenText(','));
		}
		
		### LIMIT
		
		if($tokens->seekTokenNum(SqlToken::T_LIMIT())){
			if(!$tokens->seekTokenNum(T_NUM_STRING)){
				throw new MalformedSql("Missing offset number for LIMIT part in SELECT statement!", $tokens);
			}
			$entitySelect->setLimitOffset((int)$tokens->getCurrentTokenString());
			if($tokens->seekTokenText(',')){
				if(!$tokens->seekTokenNum(T_NUM_STRING)){
					throw new MalformedSql("Missing length number for LIMIT part in SELECT statement!", $tokens);
				}
				$entitySelect->setLimitRowCount((int)$tokens->getCurrentTokenString());
			}
		}
		
		### PROCEDURE
		
		if($tokens->seekTokenNum(SqlToken::T_PROCEDURE())){
			
			/* @var $functionParser FunctionParser */
			$this->factorize($functionParser);
			
			if(!$functionParser->canParseTokens($tokens)){
				throw new MalformedSql("Missing valid procedure specifier after PROCEDURE!", $tokens);
			}
			$entitySelect->setProcedure($functionParser->convertSqlToJob($tokens));
		}
		
		### INTO OUTFILE|DUMPFILE
		
		if($tokens->seekTokenNum(SqlToken::T_INTO())){
			if(!$tokens->seekTokenNum(SqlToken::T_OUTFILE()) && !$tokens->seekTokenNum(SqlToken::T_DUMPFILE())){
				throw new MalformedSql("Missing OUTFILE or DUMPFILE after INTO!", $tokens);
			}
			if(!$tokens->seekTokenNum(T_CONSTANT_ENCAPSED_STRING)){
				throw new MalformedSql("Missing escaped string after INTO OUTFILE!");
			}
			$entitySelect->setIntoOutFile($tokens->seekTokenText($searchToken));
		}
		
		### FOR UPDATE
		
		if($tokens->seekTokenNum(SqlToken::T_FOR())){
			if(!$tokens->seekTokenNum(SqlToken::T_UPDATE())){
				throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
			}
			$entitySelect->setIsForUpdate(true);
		}
		
		### LOCK IN SHARE MODE
		
		if($tokens->seekTokenNum(SqlToken::T_LOCK())){
			if(!$tokens->seekTokenNum(SqlToken::T_IN())){
				throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
			}
			if(!$tokens->seekTokenNum(SqlToken::T_SHARE())){
				throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
			}
			if(!$tokens->seekTokenNum(SqlToken::T_MODE())){
				throw new MalformedSql("Missing UPDATE after FOR on FOR UPDATE parameter in SELECT statement!", $tokens);
			}
			$entitySelect->setIsLockInShareMode(true);
		}
		
		### UNION
		
		if($tokens->seekTokenNum(SqlToken::T_UNION())){
			
			$isUnionAll      = $tokens->seekTokenNum(SqlToken::T_ALL());
			$isUnionDistinct = $tokens->seekTokenNum(SqlToken::T_DISTINCT());
			$isUnionAll      = $isUnionAll || $tokens->seekTokenNum(SqlToken::T_ALL());
			
			if($isUnionAll && $isUnionDistinct){
				throw new MalformedSql("UNION cannot be ALL and DISTINCT at the same time!", $tokens);
			}
			
			$isUnionInParenthesis = $tokens->seekTokenText('(');
			
			if(!$this->canParseTokens($tokens)){
				throw new MalformedSql("Missing following SELECT statement after UNION in SELECT statement!", $tokens);
			}
			$entitySelect->setUnionSelect($this->convertSqlToJob($tokens), $isUnionDistinct);
			
			if($isUnionInParenthesis && !$tokens->seekTokenText(')')){
				throw new MalformedSql("Missing ending parenthesis after UNION in SELECT statement!", $tokens);
			}
		}
		
		return $entitySelect;
	}
	
}