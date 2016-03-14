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

namespace Addiks\PHPSQL\Database\DatabaseAdapter;

use Addiks\PHPSQL\Database\DatabaseAdapter\InternalDatabaseAdapter;
use Addiks\PHPSQL\Filesystem\InmemoryFilesystem;
use Addiks\PHPSQL\SqlParser\SqlParser;
use Addiks\PHPSQL\Filesystem\TransactionalFilesystem;

class InmemoryDatabaseAdapter extends InternalDatabaseAdapter
{

    public function getFilesystem()
    {
        if (is_null($this->filesystem)) {
            $this->filesystem = new TransactionalFilesystem(new InmemoryFilesystem());
        }

        return $this->filesystem;
    }

    public function getTypeName()
    {
        return 'inmemory';
    }
}
