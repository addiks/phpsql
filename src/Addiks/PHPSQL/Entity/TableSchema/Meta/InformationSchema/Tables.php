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

namespace Addiks\PHPSQL\Entity\TableSchema\Meta\InformationSchema;

class Tables extends InformationSchema
{
    
    protected function getInternalColumns()
    {
        $columns = array();
        $columnPage = new Column();
        
        $columnPage->setName("TABLE_CATALOG");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(512);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_SCHEMA");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(64);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_NAME");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(64);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_TYPE");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(64);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("ENGINE");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(64);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("VERSION");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("ROW_FORMAT");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(10);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_ROWS");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("AVG_ROW_LENGTH");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("DATA_LENGTH");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("MAX_DATA_LENGTH");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("INDEX_LENGTH");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("DATA_FREE");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("AUTO_INCREMENT");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("CREATE_TIME");
        $columnPage->setDataType(DataType::DATETIME());
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("UPDATE_TIME");
        $columnPage->setDataType(DataType::DATETIME());
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("CHECK_TIME");
        $columnPage->setDataType(DataType::DATETIME());
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_COLLATION");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(32);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("CHECKSUM");
        $columnPage->setDataType(DataType::BIGINT());
        $columnPage->setLength(21);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("CREATE_OPTIONS");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(32);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        $columnPage->setName("TABLE_COMMENT");
        $columnPage->setDataType(DataType::VARCHAR());
        $columnPage->setLength(32);
        $columns[$columnPage->getName()] = clone $columnPage;
        
        return $columns;
    }
}
