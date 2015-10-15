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

namespace Addiks\PHPSQL\Result;

use Addiks\PHPSQL\Table\TableInterface;

interface ResultInterface extends TableInterface
{

    /**
     * @return bool
     */
    public function getIsSuccess();

    /**
     * @return array
     */
    public function getHeaders();
    
    /**
     * @return array
     */
    public function getLastInsertId();
    
    /**
     * @return array
     */
    public function fetch();
    
    /**
     * @return array
     */
    public function fetchArray();
    
    /**
     * @return array
     */
    public function fetchAssoc();
    
    /**
     * @return array
     */
    public function fetchRow();
}
