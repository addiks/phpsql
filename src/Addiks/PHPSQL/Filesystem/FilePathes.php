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

namespace Addiks\PHPSQL\Filesystem;

class FilePathes
{
    
    const FILEPATH_SCHEMA             = "%s.schema";
    const FILEPATH_VIEW_SQL           = "%s/views/%s.sql";
    const FILEPATH_TABLE_SCHEMA       = "%s/tables/%s.schema";
    const FILEPATH_TABLE_INDEX_SCHEMA = "%s/tables/%s.index.schema";
    const FILEPATH_AUTOINCREMENT      = "%s/tables/%s/auto_increment.int";
    const FILEPATH_DELETED_ROWS       = "%s/tables/%s/deleted_rows.dat";
    const FILEPATH_TABLE_COLUMN_INDEX = "%s/tables/%s/indices/%s.columnindex";
    const FILEPATH_KEY_LENGTH         = "%s/tables/%s/indices/%s.keylength";
    const FILEPATH_INDEX_DATA         = "%s/tables/%s/indices/%s.data";
    const FILEPATH_INDEX_DOUBLES      = "%s/tables/%s/indices/%s.doubles";
    const FILEPATH_DEFAULT_VALUE      = "%s/tables/%s/column_data/%s/default.dat";
    const FILEPATH_COLUMN_DATA_FILE   = "%s/tables/%s/column_data/%s/%s.dat";
    const FILEPATH_COLUMN_DATA_FOLDER = "%s/tables/%s/column_data/%s";
    const FILEPATH_GROUPBY_HASHTABLE  = "temporary/groupby_hashtables/%s.hashtable";
}
