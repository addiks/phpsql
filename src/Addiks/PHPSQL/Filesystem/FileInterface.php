<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Filesystem;

interface FileInterface
{

    public function close();

    public function write($data);

    public function read($length);

    public function truncate($size);

    public function seek($offset, $seekMode = SEEK_SET);

    public function tell();

    public function eof();

    public function lock($mode);

    public function flush();

    public function getSize();

    public function readLine();

    public function getData();

    public function setData($data);

    public function addData($data);

    public function getLength();

}
