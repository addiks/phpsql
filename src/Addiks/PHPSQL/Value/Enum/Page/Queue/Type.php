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

namespace Addiks\PHPSQL\Value\Enum\Page\Queue;

use Addiks\Common\Value\Enum;

class Type extends Enum
{
    
    ### SOURCE-POINTER
    
    /**
     * This changes the active source-table-id to another table.
     * Read operations will read from that table after that queue-page was processed.
     * Changing the active table will clear the active source-column (enforces new column-set).
     * Removes the SHARED-LOCK on old source-table and creates new SHARED-LOCK on new source-table.
     * @var int
     */
    const SRC_TABLE = 0x01;
    
    /**
     * This changes the internal source-column-id to another column.
     * Read operations will read from that column after that queue-page was processed.
     * Data of queue-page contains column-id in table.
     * @var int
     */
    const SRC_COLUMN = 0x02;
    
    /**
     * This changes the internal source-row-id to another row.
     * Read operations will read from that row after that queue-page was processed.
     * @var int
     */
    const SRC_ROW = 0x03;
    
    /**
     * This increments the row index al long as condition in data is computed to 'false'.
     * (Skipping nulled rows and stopping when table-end was reached.)
     * @var unknown_type
     */
    const SRC_ROW_CONDITIONAL_INCREMENT = 0x04;
    
    ### DESTINATION-POINTER
    
    /**
     * This changes the internal destination-table-id to another table.
     * Write operations will write into that other table after that queue-page was processed.
     * @var int
     */
    const DST_TABLE = 0x10;
    
    /**
     * This changes the internal destination-column-id to another column.
     * Write operations will write into that other column after that queue-page was processed.
     * @var int
     */
    const DST_COLUMN = 0x11;
    
    /**
     * This changes the internal destination-row-id to another row.
     * Write operations will write into that other row after that queue-page was processed.
     * Can be null. In case its nulled a new row will be appended to dst-table and and te dst-row-pointer set to that row.
     * @var int
     */
    const DST_ROW = 0x12;
    
    ### TMP-TABLES
    
    /**
     * This creates a temporary result table.
     * Following Data holds table-schema (array of column-pages) of result-table.
     * After result-table was created, its table-id is written into DST_TABLE.
     * This temporary-table will be used as query-result when finished.
     * @var int
     */
    const CREATE_RESULT_TABLE = 0x20;
    
    /**
     * This creates a temporary table.
     * Following Data holds table-schema (array of column-pages) of result-table.
     * After temporary-table was created, DST_TABLE is changed to write into this table.
     * @var int
     */
    const CREATE_TEMPORARY_TABLE = 0x21;
    
    ### COMMANDS
    
    /**
     * This copies data from 'source' (SRC_TABLE, SRC_COLUMN, SRC_ROW) to 'destination' (DST_TABLE, DST_COLUMN[, DST_ROW]).
     * @var int
     */
    const COMMAND_COPY = 0x30;
    
    /**
     * This turnes the current cell (DST_TABLE, DST_COLUMN[, DST_ROW]) into a null-value.
     * @var int
     */
    const COMMAND_NULLIFY = 0x31;
    
    /**
     * This deletes the row DST_ROW is pointing to.
     * @var int
     */
    const COMMAND_DELETEROW = 0x32;
    
    /**
     * This truncates the whole table DST_TABLE into empty state.
     * @var int
     */
    const COMMAND_TRUNCATE = 0x33;
    
    ### TRANSACTIONS
    
    /**
     * This marks the beginning of one transaction.
     * Transaction-management is NOT on this level, it is only for information-purpose.
     * Other processes can look at the queue what transaction is following theirs with that information.
     * @var int
     */
    const TRANSACTION_BEGIN   = 0x40;
    
    /**
     * This marks the beginning of one transaction.
     * Transaction-management is NOT on this level, it is only for information-purpose.
     * Other processes can look at the queue what transaction is preceeding theirs with that information.
     * @var int
     */
    const TRANSACTION_END  = 0x41;
}
